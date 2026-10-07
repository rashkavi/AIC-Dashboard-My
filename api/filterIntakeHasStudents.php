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

                    $intake_id = isset($decoded['id']) ? $decoded['id'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                    $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                    $sortById = isset($decoded['sortById']) ? $decoded['sortById'] : 0;
                    $update = isset($decoded['update']) ? $decoded['update'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $selectStudents = isset($decoded['selectStudents']) ? $decoded['selectStudents'] : [];
                    $unSelectStudents = isset($decoded['unSelectStudents']) ? $decoded['unSelectStudents'] : [];
                    if ($intake_id == 0) {
                        $status->error = 'Invalid Intake ID';
                    } else {
                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                        $query = "
                        SELECT 
                            `shi`.`students_has_intakes_id` AS `shiId`,
                            `s`.`student_id` AS `sId`,
                            `shi`.`student_id`,
                            `ld`.`first_name`,
                            `ld`.`last_name`,
                            `ld`.`email`,
                            `s`.`mobile`,
                            `s`.`profile_img`,
                            `shi`.`addmission_date`,
                            `t`.`title`,
                            `st`.`status_id`,
                            `st`.`status`,
                             `ld`.`verification`,
                            `ld`.`reset_time`
                        FROM `students_has_intakes` AS `shi`
                        INNER JOIN `students` AS `s` 
                            ON `shi`.`students_student_id` = `s`.`student_id`
                        INNER JOIN `login_details` AS `ld`
                            ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                        INNER JOIN `intakes` AS `i`
                            ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                        INNER JOIN `programmes` AS `p`
                            ON `i`.`programmes_programme_id` = `p`.`programme_id`
                        INNER JOIN `faculties` AS `f`
                            ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
                        INNER JOIN `title` AS `t`
                            ON `s`.`title_title_id` = `t`.`title_id`
                        INNER JOIN `status` AS `st`
                            ON `shi`.`status_status_id` = `st`.`status_id`
                        WHERE `shi`.`intakes_intake_id` = '" . $intake_id . "'
                        AND `s`.`enrolled_status` = 'ENROLLED'
                        AND (`shi`.`lead_student_status_lead_student_status_id` IS NULL OR `shi`.`lead_student_status_lead_student_status_id` = '17')

                    ";


                        if ($search_text != "") {
                            $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%')";
                        }

                        if ($sort != 0) {
                            if (strpos($query, 'WHERE') == false) {
                                $query .= " WHERE `shi`.`status_status_id` = '" . $sort . "'";
                            } else {
                                $query .= " AND `shi`.`status_status_id` = '" . $sort . "'";
                            }
                        }

                        if ($sortByName != 0) {
                            $sort = $sortByName == 1 ? 'ASC' : 'DESC';
                            if (strpos($query, 'ORDER BY') === false) {
                                $query .= " ORDER BY `first_name` " . $sort;
                            } else {
                                $query .= ", `first_name` " . $sort;
                            }
                        }

                        if ($sortByDate != 0) {
                            $sort = $sortByDate == 1 ? 'ASC' : 'DESC';
                            if (strpos($query, 'ORDER BY') === false) {
                                $query .= " ORDER BY `shi`.`students_has_intakes_id` " . $sort;
                            } else {
                                $query .= ", `shi`.`students_has_intakes_id` " . $sort;
                            }
                        }

                        if ($sortById != 0) {
                            $sort = $sortById == 1 ? 'ASC' : 'DESC';
                            if (strpos($query, 'ORDER BY') === false) {
                                $query .= " ORDER BY `shi`.`student_id` " . $sort;
                            } else {
                                $query .= ", `shi`.`student_id` " . $sort;
                            }
                        }

                        if ($sortByName == 0 && $sortByDate == 0 && $sortById == 0) {
                            $query .= " ORDER BY `shi`.`students_has_intakes_id` DESC";
                        }

                        $isUpdateStudentStatus = $update == 1 ? true : ($update == 2 ? true : false);

                        $results = Database::search($query);
                        $results_count = $results->num_rows;
                        $result_per_page = $resultPerPage;

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
                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleIntakeHasStudentPagination(<?= $intake_id ?>,'sortByName')">
                                                <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                <?php
                                                if ($sortByName == 0) {
                                                ?>
                                                    <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                <?php
                                                } else {
                                                ?>
                                                    <iconify-icon icon="<?= $sortByName == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasStudentPagination(<?= $intake_id ?>,'sortById')">
                                        <div class="d-flex flex-row align-items-center justify-content-between cursor-pointer">
                                            <span class="text-14 main-text fw-medium me-2">Student Id</span>
                                            <?php
                                            if ($sortById == 0) {
                                            ?>
                                                <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                            <?php
                                            } else {
                                            ?>
                                                <iconify-icon icon="<?= $sortById == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Mobile</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasStudentPagination(<?= $intake_id ?>,'sortByDate')">
                                        <div class="d-flex flex-row align-items-center justify-content-between cursor-pointer">
                                            <span class="text-14 main-text fw-medium me-2">Admission Date</span>
                                            <?php
                                            if ($sortByDate == 0) {
                                            ?>
                                                <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                            <?php
                                            } else {
                                            ?>
                                                <iconify-icon icon="<?= $sortByDate == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </th>

                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                    <?php
                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('intake_students', 1)) {
                                    ?>
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Action</th>

                                    <?php
                                    }
                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $intake_id) || UserPermissions::hasPermission('intake_students', 10, $intake_id)) {
                                    ?>
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                    <?php
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody id="user-table-data">
                                <?php
                                if ($students->num_rows > 0) {
                                    while ($data = $students->fetch_assoc()) {
                                ?>
                                        <tr>
                                            <td class="px-4 py-3 pe-0 align-middle">
                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                    <?php
                                                    $isMatchSelectUpdate = false;
                                                    if ($isUpdateStudentStatus) {
                                                        if (count($selectStudents) > 0) {
                                                            if (in_array($data['shiId'], $selectStudents)) {
                                                                if ($data['status_id'] == 1 && $update == 2) {
                                                                    $isMatchSelectUpdate = true;
                                                                } else if ($data['status_id'] == 2 && $update == 1) {
                                                                    $isMatchSelectUpdate = true;
                                                                }
                                                            }
                                                        }

                                                        if (count($unSelectStudents) > 0) {
                                                            if (in_array($data['shiId'], $unSelectStudents)) {
                                                                $isMatchSelectUpdate = false;
                                                            }
                                                        }

                                                        if (count($selectStudents) == 0 && count($unSelectStudents) == 0) {
                                                            if ($data['status_id'] == 1 && $update == 2) {
                                                                $isMatchSelectUpdate = true;
                                                            } else if ($data['status_id'] == 2 && $update == 1) {
                                                                $isMatchSelectUpdate = true;
                                                            }
                                                        }
                                                    }

                                                    if ($isMatchSelectUpdate) {
                                                    ?>
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" checked id="check-user<?php echo $data["shiId"]; ?>" onchange="checkRowEvents(<?php echo $data['shiId']; ?>);">
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["shiId"]; ?>" onchange="checkRowEvents(<?php echo $data['shiId']; ?>);">
                                                    <?php
                                                    }

                                                    if (!empty($data["profile_img"]) && file_exists('../assets/imgs/students/' . $data["profile_img"])) {
                                                    ?>
                                                        <img src="./assets/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="User image">
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
                                            </td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["student_id"]; ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["mobile"]; ?></span></td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["addmission_date"])); ?></span></td>

                                            <td class="py-2 align-middle">
                                                <?php
                                                $status_classes = [
                                                    "Active"     => "table-btn-success",
                                                    "Inactive"  => "table-btn-danger",
                                                ];
                                                $sbutton_class = $status_classes[$data["status"]];
                                                ?>
                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["status"]; ?></button>
                                            </td>

                                            <?php
                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('intake_students', 1)) {
                                            ?>
                                                <td class="pt-3 pb-3 align-middle">
                                                    <button class="table-btn table-btn-dark-theme active w-100" onclick="window.location.href='edit-intake-student?student-id=<?= $data['shiId']; ?>'">View</button>
                                                </td>
                                            <?php
                                            }
                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('intake_students', 10, $intake_id) || UserPermissions::hasPermission('intake_payments', 10, $intake_id) || UserPermissions::hasPermission('intake_payments', 12, $intake_id) || UserPermissions::hasPermission('intake_payments', 13, $intake_id)) {
                                            ?>
                                                <td class="py-3 align-middle">
                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                    <div class="dropdown">
                                                        <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">

                                                            <?php

                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('intake_students', 10, $intake_id)) {
                                                                if (!empty($data['verification']) && empty($data['reset_time'])) {
                                                            ?>
                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendStudentRegistrationEmail('<?php echo $data['sId']; ?>')">
                                                                            <iconify-icon icon="lets-icons:send-fill" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                            Resend Registration Link</a></li>
                                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendStudentRegistrationEmail('<?php echo $data['sId']; ?>')">
                                                                            <iconify-icon icon="solar:password-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                            Resend Password Reset Link</a></li>
                                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                <?php
                                                                }
                                                                ?>
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="edit-intake-student?student-id=<?php echo $data['shiId']; ?>">
                                                                        <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                        Edit</a></li>
                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                            <?php
                                                            }
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 10, $intake_id) || UserPermissions::hasPermission('intake_payments', 12, $intake_id) || UserPermissions::hasPermission('intake_payments', 13, $intake_id)) {
                                                            ?>
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="intake-student-payments?student-id=<?php echo $data['shiId']; ?>">
                                                                        <iconify-icon icon="iconoir:wallet" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                        Payments</a></li>
                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                <?php
                                                            }

                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('intake_students', 4, $intake_id)) {

                                                                if ($data["status"] == "Active") {
                                                                ?>
                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateIntakeStudentStatus(2,<?= $intake_id ?>,'<?php echo $data['shiId']; ?>')">
                                                                            <iconify-icon icon="lsicon:minus-filled" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                            Deactivate</a></li>
                                                                <?php
                                                                } else if ($data["status"] == "Inactive") {
                                                                ?>
                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateIntakeStudentStatus(1,<?= $intake_id ?>,'<?php echo $data['shiId']; ?>')">
                                                                            <iconify-icon icon="dashicons:plus-alt" width="20" height="20" class="sub-text me-3"></iconify-icon>
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
                        <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container3">
                            <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                <div class="row p-4 align-items-center">
                                    <!-- Previous Button (col-1) -->
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleIntakeHasStudentPagination(<?= $intake_id ?>,'previousPage');">
                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                    </button>
                                    <!-- Page Numbers (col-10, centered) -->
                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                        <ul class="pagination-list d-flex mb-0">
                                            <?php
                                            $shudderPages1 = false;
                                            $shudderPages2 = false;

                                            function renderPageButton($intake_id, $i, $isActive = false)
                                            {
                                                $activeClass = $isActive ? 'active' : '';
                                                $action = $isActive ? '' : "onclick=\"handleIntakeHasStudentPagination('{$intake_id}','goToPage', '{$i}')\"";
                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                            }

                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                    renderPageButton($intake_id, $i, $pageno == $i);
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
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleIntakeHasStudentPagination(<?= $intake_id ?>,'nextPage',<?= $number_of_pages ?>);">
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
