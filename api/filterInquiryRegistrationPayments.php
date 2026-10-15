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
                    $branch = isset($decoded['branch']) ? $decoded['branch'] : 0;
                    $faculty = isset($decoded['faculty']) ? $decoded['faculty'] : 0;
                    $programme = isset($decoded['programme']) ? $decoded['programme'] : 0;
                    $intake = isset($decoded['intake']) ? $decoded['intake'] : 0;
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                    $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $query = "
                                                         SELECT 
                                                             `students`.`student_id` AS 'sId', 
                                                         
                                                             `login_details`.`first_name`, 
                                                             `login_details`.`last_name`, 
                                                             `login_details`.`email`, 
                                                         
                                                             `students`.`mobile`, 
                                                         
                                                             `login_details`.`whatsapp`, 
                                                             `students`.`profile_img`, 
                                                             `login_details`.`date_registered`, 
                                                         
                                                             `title`.`title`, 
                                                         
                                                             `lead_student_status`.`lead_student_status`,
                                                             `lead_student_status`.`lead_student_status_id`,
                                                         
                                                             `login_details`.`verification`,
                                                             `login_details`.`reset_time`,
                                                         
                                                             `users`.`user_fname`,
                                                             `users`.`user_lname`,
                                                         
                                                             `title2`.`title` AS `user_title`,
                                                         
                                                             `sl`.`students_log_id`,
                                                             `sl`.`date_updated` AS `latest_log_date`,
                                                             `sl`.`log` AS `latest_log`,
                                                             `sl`.`note` AS `latest_note`,

                                                             `students_has_intakes`.`students_has_intakes_id` AS `shiId`,

                                                             
                                                         
                                                             `updated_user`.`user_id` AS `updated_user_id`,
                                                             `updated_user`.`user_fname` AS `updated_user_fname`,
                                                             `updated_user`.`user_lname` AS `updated_user_lname`,
                                                         
                                                             `updated_user_title`.`title` AS `updated_user_title`
                                                         
                                                         FROM `students`
                                                         
                                                         INNER JOIN `login_details`
                                                             ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                         
                                                         INNER JOIN `title`
                                                             ON `students`.`title_title_id` = `title`.`title_id`

                                                        INNER JOIN `students_has_intakes`
                                                            ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
                                                         
                                                         INNER JOIN `lead_student_status`
                                                             ON `students_has_intakes`.`lead_student_status_lead_student_status_id` = `lead_student_status`.`lead_student_status_id`
                                                         
                                                         INNER JOIN `users`
                                                             ON `students`.`users_user_id` = `users`.`user_id`
                                                         
                                                         INNER JOIN `title` AS `title2`
                                                             ON `users`.`title_title_id` = `title2`.`title_id`
                                                         
                                                         LEFT JOIN `students_logs` AS `sl`
                                                             ON `sl`.`students_log_id` = (
                                                                 SELECT `sl2`.`students_log_id`
                                                                 FROM `students_logs` AS `sl2`
                                                                 WHERE `sl2`.`students_has_intakes_students_has_intakes_id` = `students_has_intakes`.`students_has_intakes_id`
                                                                   AND `sl2`.`lead_student_status_lead_student_status_id` IN (2,3,4)
                                                                 ORDER BY `sl2`.`date_updated` DESC
                                                                 LIMIT 1
                                                             )
                                                         
                                                         LEFT JOIN `users` AS `updated_user`
                                                             ON `sl`.`users_user_id` = `updated_user`.`user_id`
                                                         
                                                         LEFT JOIN `title` AS `updated_user_title`
                                                             ON `updated_user`.`title_title_id` = `updated_user_title`.`title_id`
                                                         
                                                           WHERE 1=1
                                                         ";

                    if ($search_text != "") {
                        $query .= " AND (`students`.`student_id` LIKE '%" . $search_text . "%' OR `first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%')";
                    }

                    if ($branch != 0) {
                        $query .= " AND `faculties`.`branches_branch_id` = '" . $branch . "'";
                    }

                    if ($faculty != 0) {
                        $query .= " AND `faculties`.`faculty_id` = '" . $faculty . "'";
                    }

                    if ($programme != 0) {
                        $query .= " AND `programmes`.`programme_id` = '" . $programme . "'";
                    }

                    if ($intake != 0) {
                        $query .= " AND `intakes`.`intake_id` = '" . $intake . "'";
                    }

                    if ($sort != 0) {
                        $query .= " AND `lead_student_status`.`lead_student_status_id` = '" . $sort . "'";
                    }

                    $query .= "  GROUP BY `students_has_intakes`.`students_has_intakes_id`
                                                         ";

                    $order_key = " ORDER BY";

                    if ($sortByName != 0) {
                        $sort = $sortByName == 1 ? 'ASC' : 'DESC';
                        $query .= " " . $order_key . " `first_name` " . $sort;
                        $order_key = ", ";
                    }

                    if ($sortByDate != 0) {
                        $sort = $sortByDate == 1 ? 'ASC' : 'DESC';
                        $query .= " " . $order_key . " `students`.`student_id` " . $sort;
                    }

                    if ($sortByName == 0 && $sortByDate == 0) {
                        $query .= " " . $order_key . " `students_has_intakes`.`students_has_intakes_id` DESC";
                    }

                    $results = Database::search($query);
                    $results_count = $results->num_rows;
                    $result_per_page = (int)$resultPerPage;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $students = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");
?>
                    <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                        <thead>
                            <tr class="br-t-5">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                    <div class="d-flex flex-row align-items-center cursor-pointer">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleInquiryRegistrationPaymentsPagination('sortByName')">
                                            <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                        </div>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleInquiryRegistrationPaymentsPagination('sortByDate')">
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <span class="text-14 main-text fw-medium">Date Added</span>
                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Added By</th>
                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleInquiryRegistrationPaymentsPagination('sortByDate')">
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <span class="text-14 main-text fw-medium">Date Updated</span>
                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Updated By</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>

                                <?php
                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole)) {
                                ?>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                <?php
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody id="students-table-data" data-student-count="<?php echo $results_count; ?>">
                            <?php

                            if ($students->num_rows > 0) {
                                while ($data = $students->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["sId"]; ?>" onchange="checkRowEvents(<?php echo $data['sId']; ?>);">
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['sId']; ?>)">
                                                    <?php
                                                    if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                    ?>
                                                        <img src="./assets/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
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

                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["date_registered"])); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span></td>
                                        <?php
                                        if ($data["lead_student_status_id"] == 1) {
                                        ?>
                                            <td class="py-2 align-middle"><span class="text-14 main-text">N/A</span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text">N/A</span></td>
                                        <?php
                                        } else {
                                        ?>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["latest_log_date"])); ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["updated_user_title"] . "." . $data["updated_user_fname"] . " " . $data["updated_user_lname"]; ?></span></td>
                                        <?php
                                        }
                                        ?>

                                        <td class="py-2 align-middle">
                                            <label class="table-btn label-student-status-<?php echo $data["lead_student_status_id"]; ?> text-center w-100"> <?php echo $data["lead_student_status"]; ?></label>
                                        </td>

                                        <?php
                                        if (SessionUser::ifUserIsPro($sessionUserRole)  || SessionUser::ifUserIsAccounts($sessionUserRole)) {
                                        ?>
                                            <td class="py-3 align-middle">
                                                <div class="dropdown">
                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                    <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="registration-payment?registration-id=<?php echo $data['shiId']; ?>">
                                                                <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                View</a></li>

                                                        <?php
                                                        if ($data['lead_student_status_id'] == 4 || $data['lead_student_status_id'] > 4) {
                                                        ?>

                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-student-payments?student-id=<?php echo $data['shiId']; ?>&path=payments">
                                                                    <iconify-icon icon="stash:plan" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                    View Student Payment Plan</a></li>

                                                        <?php
                                                        }
                                                        if ($data['lead_student_status_id'] == 3 || $data['lead_student_status_id'] == 4) {
                                                        ?>
                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                            <li>
                                                                <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                    onclick="openAccountsConfirmModal(<?= $data['sId']; ?>)">
                                                                    <iconify-icon icon="solar:verified-check-bold"
                                                                        width="20"
                                                                        height="20"
                                                                        class="sub-text me-3"></iconify-icon>
                                                                    Confirm Accounts Completion
                                                                </a>
                                                            </li>
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleInquiryRegistrationPaymentsPagination('previousPage');">
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
                                            $action = $isActive ? '' : "onclick=\"handleInquiryRegistrationPaymentsPagination('goToPage', '{$i}')\"";
                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                        }

                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                renderPageButton($i, $pageno == $i);
                                            } elseif (!$shudderPages1 && ($i < $pageno - 2 && $i > 1)) {
                                                $shudderPages1 = true;
                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                            } elseif (!$shudderPages2 && ($i > $pageno + 2 && $i < $number_of_pages)) {
                                                $shudderPages2 = true;
                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                            }
                                        }
                                        ?>

                                    </ul>
                                </div>
                                <!-- Next Button (col-1) -->
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleInquiryRegistrationPaymentsPagination('nextPage',<?= $number_of_pages ?>);">
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
