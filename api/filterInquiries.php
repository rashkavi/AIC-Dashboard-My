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
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $filter_category = isset($decoded['category']) ? $decoded['category'] : '';
                    $filter_status = isset($decoded['status']) ? $decoded['status'] : '';
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;


                    $query = " SELECT
                        `students`.`profile_img`,
                        `login_details`.`first_name`,
                        `login_details`.`last_name`,
                        `help_desk_category`.`help_desk_category`,
                        `help_desk`.`title` AS `help_desk_title`,
                        `help_desk_status`.`help_desk_status_id`,
                        `help_desk_status`.`help_desk_status`,
                        `title`.`title`,
                        `students_has_help_desk`.`date_added`,
                        `students_has_help_desk`.`students_has_help_desk_id`
                    FROM `students_has_help_desk`
                    INNER JOIN `help_desk` 
                        ON `help_desk`.`help_desk` = `students_has_help_desk`.`help_desk_help_desk`
                    INNER JOIN `help_desk_category` 
                        ON `help_desk_category`.`help_desk_category_id` = `help_desk`.`help_desk_category_help_desk_category_id`
                    INNER JOIN `help_desk_status` 
                        ON `help_desk_status`.`help_desk_status_id` = `students_has_help_desk`.`help_desk_status_help_desk_status_id`
                    INNER JOIN `students` 
                        ON `students`.`student_id` = `students_has_help_desk`.`students_student_id`
                    INNER JOIN `login_details`
                        ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                    INNER JOIN `title` 
                        ON `title`.`title_id` = `students`.`title_title_id`
                ";


                    if ($sessionUserRole != "Developer") {
                        $query .= " WHERE `help_desk_category_help_desk_category_id` != '2'";
                    }

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 25))) {
                        if (strpos($query, 'WHERE') == false) {
                            $countQuery .= " WHERE `users_user_id` = '" . $sessionUserObject['user_id'] . "'";
                        } else {
                            $countQuery .= " AND `users_user_id` = '" . $sessionUserObject['user_id'] . "'";
                        }
                    }

                    if ($search_text != "") {
                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE (`first_name` LIKE '%" . $search_text . "%' OR `last_name`)";
                        } else {
                            $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `last_name`)";
                        }
                    }

                    if ($filter_category != "" && $filter_category != 0) {
                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE `help_desk_category_id` = '" . $filter_category . "'";
                        } else {
                            $query .= " AND `help_desk_category_id` = '" . $filter_category . "'";
                        }
                    }

                    if ($filter_status != "" && $filter_status != 0) {
                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE `help_desk_status_id` = '" . $filter_status . "'";
                        } else {
                            $query .= " AND `help_desk_status_id` = '" . $filter_status . "'";
                        }
                    }

                    $query .= " ORDER BY `students_has_help_desk_id` DESC";

                    $results = Database::search($query);
                    $results_count = $results->num_rows;
                    $result_per_page = (int)$resultPerPage;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $inquiries = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                    <table class="table bl-solid m-0 p-0 g-0" id="faq-table">
                        <thead>
                            <tr class="br-t-5">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium w-25">
                                    <div class="d-flex flex-row align-items-center">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all">
                                        <span class="text-14 main-text fw-medium mx-2">Student</span>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Category</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Title</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Added At</th>
                                <th scope="col" class="py-3 bg-table-header">
                                    <div class="d-flex flex-row align-items-center">
                                        <span class="text-14 main-text fw-medium">Status</span>
                                        <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php
                            if ($inquiries->num_rows > 0) {
                                $status_classes = [
                                    "1"  => "table-btn-yellow",
                                    "2"  => "table-btn-primary",
                                    "3"  => "table-btn-danger",
                                    "4"  => "table-btn-orange",
                                    "5"  => "table-btn-success",
                                ];
                                while ($data = $inquiries->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                    <?php
                                                    if (!empty($data["profile_img"]) && file_exists(Project::getBasePortalUrl() . '/assets/imgs/students/' . $data["profile_img"])) {
                                                    ?>
                                                        <img src="<?= Project::getBasePortalUrl() ?>/assets/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                    <?php
                                                    } else {
                                                        $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                        $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                    ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                    <?php
                                                    }
                                                    ?>
                                                    <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["help_desk_category"]; ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["help_desk_title"]; ?></span></td>
                                        <td class="py-2 align-middle">
                                            <span class="text-14 main-text"><?php echo $data["date_added"]; ?>
                                        </td>
                                        <td class="py-2 align-middle">
                                            <?php

                                            $sbutton_class = $status_classes[$data["help_desk_status_id"]];
                                            ?>
                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["help_desk_status"]; ?></button>
                                        </td>

                                        <td class="py-3 align-middle">
                                            <div class="dropdown">
                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-inquiry?inquiry-id=<?php echo $data['students_has_help_desk_id']; ?>">
                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                            View</a></li>
                                                </ul>
                                            </div>
                                        </td>
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleInquiriesPagination('previousPage')">
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
                                            $action = $isActive ? '' : "onclick=\"handleInquiriesPagination('goToPage', '{$i}')\"";
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleInquiriesPagination('nextPage',null,'<?= $number_of_pages ?>')">
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
