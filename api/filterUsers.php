<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $sessionUserObject = SessionUser::getCurrentUserObject();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $user_role = isset($decoded['role']) ? $decoded['role'] : 0;
                    $user_sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $sort_name = isset($decoded['sortName']) ? $decoded['sortName'] : '';
                    $sort_date = isset($decoded['sortDate']) ? $decoded['sortDate'] : '';
                    $user_update = isset($decoded['update']) ? $decoded['update'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $selectUsers = isset($decoded['selectUsers']) ? $decoded['selectUsers'] : [];
                    $unSelectUsers = isset($decoded['unSelectUsers']) ? $decoded['unSelectUsers'] : [];

                    $query = "SELECT 
                            `users`.`user_id`, 
                            `users`.`user_fname`, 
                            `users`.`user_lname`, 
                            `users`.`user_email`, 
                            `users`.`user_mobile`, 
                            `users`.`user_reg_date`,
                            `user_roles`.`user_role`, 
                            `user_roles`.`user_role_id`, 
                            `title`.`title`, 
                            `status`.`status`,
                            `status`.`status_id`,
                            `users`.`status_status_id`,
                            `users`.`profile_img`,
                             `user_verification`,
                                                                    `user_reset_time`
                        FROM `users` 
                        INNER JOIN `user_roles` 
                            ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id` 
                        INNER JOIN `title` 
                            ON `users`.`title_title_id` = `title`.`title_id` 
                        INNER JOIN `status` 
                            ON `users`.`status_status_id` = `status`.`status_id`
                        ";

                    if ($search_text != "") {
                        $query .= " WHERE `user_fname` LIKE '%" . $search_text . "%' OR `user_lname` LIKE '%" . $search_text . "%' OR `user_email` LIKE '%" . $search_text . "%' OR `user_mobile` LIKE '%" . $search_text . "%'";
                    }

                    if ($user_role != 0) {
                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE `users`.`user_roles_user_role_id` = '" . $user_role . "'";
                        } else {
                            $query .= " AND `users`.`user_roles_user_role_id` = '" . $user_role . "'";
                        }
                    }

                    if ($user_sort != 0) {

                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE `users`.`status_status_id` = '" . $user_sort . "'";
                        } else {
                            $query .= " AND `users`.`status_status_id` = '" . $user_sort . "'";
                        }
                    }

                    if ($sort_name != 0) {
                        $sort = $sort_name == 1 ? 'ASC' : 'DESC';
                        if (strpos($query, 'ORDER BY') === false) {
                            $query .= " ORDER BY `user_fname` " . $sort;
                        } else {
                            $query .= ", `user_fname` " . $sort;
                        }
                    }

                    if ($sort_date != 0) {
                        $sort = $sort_date == 1 ? 'ASC' : 'DESC';
                        if (strpos($query, 'ORDER BY') === false) {
                            $query .= " ORDER BY `user_reg_date` " . $sort;
                        } else {
                            $query .= ", `user_reg_date` " . $sort;
                        }
                    }


                    if ($sort_name == 0 && $sort_date == 0) {
                        $query .= " ORDER BY `user_reg_date` DESC";
                    }

                    $isUserUpdateStatus = $user_update == 1 ? true : ($user_update == 2 ? true : false);

                    $results = Database::search($query);
                    $results_count = $results->num_rows;
                    $result_per_page = (int)$resultPerPage;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                        <thead>
                            <tr class="br-t-5">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                    <div class="d-flex flex-row align-items-center cursor-pointer">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">

                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleUsersPagination('sortByName')">
                                            <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                            <?php
                                            if ($sort_name == 0) {
                                            ?>
                                                <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                            <?php
                                            } else {
                                            ?>
                                                <iconify-icon icon="<?= $sort_name == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </th>

                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Mobile</th>
                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleUsersPagination('sortByDate')">
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <span class="text-14 main-text fw-medium">Date Registered</span>
                                        <?php
                                        if ($sort_date == 0) {
                                        ?>
                                            <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                        <?php
                                        } else {
                                        ?>
                                            <iconify-icon icon="<?= $sort_date == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                        <?php
                                        }
                                        ?>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">User Role</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                <?php
                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 4) || UserPermissions::hasPermission('users', 10)) {
                                ?>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                <?php
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php
                            if ($users->num_rows > 0) {
                                while ($data = $users->fetch_assoc()) {
                                    $sbutton_class = "label-role-" . $data["user_role_id"];
                            ?>
                                    <tr>
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <?php
                                                $isMatchSelectUpdate = false;
                                                if ($isUserUpdateStatus) {
                                                    if (count($selectUsers) > 0) {
                                                        if (in_array($data['user_id'], $selectUsers)) {
                                                            if ($data['status_status_id'] == 1 && $user_update == 2) {
                                                                $isMatchSelectUpdate = true;
                                                            } else if ($data['status_status_id'] == 2 && $user_update == 1) {
                                                                $isMatchSelectUpdate = true;
                                                            }
                                                        }
                                                    }

                                                    if (count($unSelectUsers) > 0) {
                                                        if (in_array($data['user_id'], $unSelectUsers)) {
                                                            $isMatchSelectUpdate = false;
                                                        }
                                                    }

                                                    if (count($selectUsers) == 0 && count($unSelectUsers) == 0) {
                                                        if ($data['status_status_id'] == 1 && $user_update == 2) {
                                                            $isMatchSelectUpdate = true;
                                                        } else if ($data['status_status_id'] == 2 && $user_update == 1) {
                                                            $isMatchSelectUpdate = true;
                                                        }
                                                    }
                                                }

                                                if ($isMatchSelectUpdate) {
                                                ?>
                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" checked id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                <?php
                                                } else {
                                                ?>
                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                <?php
                                                }
                                                ?>
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['user_id']; ?>)">
                                                    <?php if (!empty($data["profile_img"]) && file_exists('../assets/imgs/users/' . $data["profile_img"])) {
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
                                            </div>
                                        </td>
                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?php echo $data["user_email"]; ?></span></td>
                                        <td class="py-3 align-middle"><span class="text-14 main-text"><?php echo $data["user_mobile"]; ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"> <?php echo date("Y-m-d h:i A", strtotime($data["user_reg_date"])); ?></span></td>
                                        <td class="py-2 align-middle">
                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["user_role"]; ?></button>
                                        </td>
                                        <td class="py-3 align-middle">
                                            <?php
                                            $status_classes = [
                                                "1"     => "table-btn-success",
                                                "2"  => "table-btn-danger",
                                            ];
                                            $sbutton_class = $status_classes[$data["status_id"]];
                                            ?>
                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["status"]; ?></button>
                                        </td>

                                        <?php
                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 4) || UserPermissions::hasPermission('users', 10)) {
                                        ?>
                                            <td class="py-3 align-middle">
                                                <div class="dropdown">
                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                    <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">

                                                        <?php
                                                        if (!empty($data['user_verification']) && empty($data['user_reset_time'])) {
                                                        ?>
                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendUserRegistrationEmail('<?php echo $data['user_id']; ?>')">
                                                                    <iconify-icon icon="lets-icons:send-fill" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                    Resend Registration Email</a></li>
                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <?php
                                                        } else {
                                                        ?>
                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendUserRegistrationEmail('<?php echo $data['user_id']; ?>')">
                                                                    <iconify-icon icon="solar:password-broken" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                    Resend Password Reset Email</a></li>
                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <?php
                                                        }
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 10)) {
                                                        ?>
                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-user?user-id=<?php echo $data['user_id']; ?>">
                                                                    <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                    Edit</a></li>
                                                        <?php
                                                        }
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || (UserPermissions::hasPermission('users', 4) && UserPermissions::hasPermission('users', 10))) {
                                                        ?>
                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <?php
                                                        }
                                                        ?>

                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 4)) {
                                                            if ($data["status"] == "Active") {
                                                        ?>
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateUserStatus(2,'<?php echo $data['user_id']; ?>')">
                                                                        <iconify-icon icon="lsicon:minus-filled" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                        Deactivate</a></li>
                                                            <?php
                                                            } else {
                                                            ?>
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateUserStatus(1,'<?php echo $data['user_id']; ?>')">
                                                                        <iconify-icon icon="bxs:plus-circle" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                        Activate</a></li>
                                                        <?php
                                                            }
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleUsersPagination('previousPage')">
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
                                            $action = $isActive ? '' : "onclick=\"handleUsersPagination('goToPage', '{$i}')\"";
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleUsersPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                    <span class="text-14 main-text fw-semibold">Next</span>
                                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                </button>
                            </div>
                        </nav>
                    </div>
<?php

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


?>