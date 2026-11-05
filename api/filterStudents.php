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
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $branch = isset($decoded['branch']) ? $decoded['branch'] : 0;
                    $faculty = isset($decoded['faculty']) ? $decoded['faculty'] : 0;
                    $programme = isset($decoded['programme']) ? $decoded['programme'] : 0;
                    $intake = isset($decoded['intake']) ? $decoded['intake'] : 0;
                    $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                    $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                    $update = isset($decoded['update']) ? $decoded['update'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $selectStudents = isset($decoded['selectStudents']) ? $decoded['selectStudents'] : [];
                    $unSelectStudents = isset($decoded['unSelectStudents']) ? $decoded['unSelectStudents'] : [];

                    $query = "
                                                           SELECT 
                                                               `students`.`student_id` AS 'sId', 
                                                               `first_name`, 
                                                               `last_name`, 
                                                               `email`, 
                                                               `mobile`, 
                                                               `whatsapp`, 
                                                               `students`.`profile_img`, 
                                                               `date_registered`, 
                                                               `title`.`title`, 
                                                               `status`.`status`,
                                                               `status`.`status_id`,
                                                               `verification`,
                                                               `reset_time`,
                                                               `users`.`user_fname`,
                                                               `users`.`user_lname`,
                                                               `title2`.`title` AS `user_title`
                                                           FROM 
                                                               `students`
                                                           INNER JOIN 
                                                               `login_details` 
                                                               ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                           INNER JOIN 
                                                               `title` 
                                                               ON `students`.`title_title_id` = `title`.`title_id`
                                                           INNER JOIN 
                                                               `status` 
                                                               ON `login_details`.`status_status_id` = `status`.`status_id`
                                                            LEFT JOIN `users`
                                                                ON `students`.`users_user_id` = `users`.`user_id`
                                                            LEFT JOIN `title` AS `title2`
                                                                ON `users`.`title_title_id` = `title2`.`title_id`
                                                          WHERE `enrolled_status` = 'ENROLLED'
                                                       ";

                    if ($search_text != "") {
                        $query .= " AND (`student_id` LIKE '%" . $search_text . "%' 
                        OR `first_name` LIKE '%" . $search_text . "%' 
                        OR `last_name` LIKE '%" . $search_text . "%' 
                        OR CONCAT(`first_name`, ' ', `last_name`) LIKE '%" . $search_text . "%'
                        OR `email` LIKE '%" . $search_text . "%' 
                        OR `mobile` LIKE '%" . $search_text . "%')";
                    }

                    if ($sort != 0) {
                        $query .= " AND `login_details`.`status_status_id` = '" . $sort . "'";
                    }

                    if (!empty($intake)) {
                        $query .= " AND `student_id` IN (SELECT `students_student_id` FROM `students_has_intakes`
                        INNER JOIN `intakes` ON `intakes`.`intake_id`=`students_has_intakes`.`intakes_intake_id` 
                        WHERE `intakes`.`intake` LIKE '%" . $intake . "%')";
                    }

                    if (!empty($programme)) {
                        $query .= " AND `student_id` IN (SELECT `students_student_id` FROM `students_has_intakes` 
                        INNER JOIN `intakes` ON `intakes`.`intake_id`=`students_has_intakes`.`intakes_intake_id`
                        INNER JOIN `programmes` ON `programmes`.`programme_id`=`intakes`.`programmes_programme_id`
                        WHERE `programmes`.`programme` LIKE '%" . $programme . "%')";
                    }

                    if (!empty($faculty)) {
                        $query .= " AND `student_id` IN (SELECT `students_student_id` FROM `students_has_intakes` 
                        INNER JOIN `intakes` ON `intakes`.`intake_id`=`students_has_intakes`.`intakes_intake_id`
                        INNER JOIN `programmes` ON `programmes`.`programme_id`=`intakes`.`programmes_programme_id`
                        INNER JOIN `faculties` ON `faculties`.`faculty_id`=`programmes`.`faculties_faculty_id`
                        WHERE `faculties`.`faculty` LIKE '%" . $faculty . "%')";
                    }
                    if (!empty($branch)) {
                        $query .= " AND `student_id` IN (SELECT `students_student_id` FROM `students_has_intakes` 
                        INNER JOIN `intakes` ON `intakes`.`intake_id`=`students_has_intakes`.`intakes_intake_id`
                        INNER JOIN `programmes` ON `programmes`.`programme_id`=`intakes`.`programmes_programme_id`
                        INNER JOIN `faculties` ON `faculties`.`faculty_id`=`programmes`.`faculties_faculty_id`
                        INNER JOIN `branches` ON `branches`.`branch_id`=`faculties`.`branches_branch_id`
                        WHERE `branches`.`branch` LIKE '%" . $branch . "%')";
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
                            $query .= " ORDER BY `students`.`student_id` " . $sort;
                        } else {
                            $query .= ", `students`.`student_id` " . $sort;
                        }
                    }

                    if ($sortByName == 0 && $sortByDate == 0) {
                        $query .= " ORDER BY `students`.`student_id` DESC";
                    }

                    $isUpdateStudentStatus = $update == 1 ? true : ($update == 2 ? true : false);

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

                    function programmeShortName($name, $short = null)
                    {
                        if (!empty($short)) {
                            return $short;
                        }
                        $skip = ['with', 'of', 'and', 'in', 'the', 'for'];
                        $words = preg_split('/[\s,()\-]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
                        $abbr = '';
                        foreach ($words as $w) {
                            if (!in_array(strtolower($w), $skip)) {
                                $abbr .= strtoupper($w[0]);
                            }
                        }
                        return $abbr;
                    }
?>
                    <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                        <thead>
                            <tr class="br-t-5">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center align-content-center w-25">
                                    <div class="d-flex flex-row align-items-center cursor-pointer">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsPagination('sortByName')">
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
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">WhatsApp Number</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Counselor</th>
                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleStudentsPagination('sortByDate')">
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <span class="text-14 main-text fw-medium">Admission Date</span>
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

                                if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAccounts($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 4) || UserPermissions::hasPermission('students', 10)) {
                                ?>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                <?php
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody id="students-table-data" data-student-count="<?= $students->num_rows ?>">
                            <?php
                            if ($students->num_rows > 0) {
                                while ($data = $students->fetch_assoc()) {
                            ?>
                                    <tr id="student-data-row">
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <?php
                                                $isMatchSelectUpdate = false;
                                                if ($isUpdateStudentStatus) {
                                                    if (count($selectStudents) > 0) {
                                                        if (in_array($data['sId'], $selectStudents)) {
                                                            if ($data['status_id'] == 1 && $update == 2) {
                                                                $isMatchSelectUpdate = true;
                                                            } else if ($data['status_id'] == 2 && $update == 1) {
                                                                $isMatchSelectUpdate = true;
                                                            }
                                                        }
                                                    }

                                                    if (count($unSelectStudents) > 0) {
                                                        if (in_array($data['sId'], $unSelectStudents)) {
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
                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" checked id="check-user<?= $data["sId"]; ?>" onchange="checkRowEvents(<?= $data['sId']; ?>);">
                                                <?php
                                                } else {
                                                ?>
                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["sId"]; ?>" onchange="checkRowEvents(<?= $data['sId']; ?>);">
                                                <?php
                                                }
                                                ?>
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?= $data['sId']; ?>)">
                                                    <?php
                                                    if (!empty($data["profile_img"]) && file_exists('../assets/imgs/students/' . $data["profile_img"])) {
                                                    ?>
                                                        <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                    <?php
                                                    } else {
                                                        $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                        $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                    ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                    <?php
                                                    }
                                                    ?>
                                                    <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["email"]; ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["whatsapp"]; ?></span></td>
                                        <td class="py-2 align-middle">
                                            <?php
                                            $counselors = Database::search("SELECT `title`.`title` AS 'c_title', `users`.`user_fname` AS 'c_fname', `users`.`user_lname` AS 'c_lname', `programmes`.`programme`
                                                                                FROM `students_has_intakes`
                                                                                INNER JOIN `users` ON `students_has_intakes`.`counselor_user_id` = `users`.`user_id`
                                                                                INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                                INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                                                                                INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                                                                WHERE `students_has_intakes`.`students_student_id` = '" . intval($data['sId']) . "'
                                                                                AND `students_has_intakes`.`status_status_id` = '1'");

                                            if ($counselors->num_rows > 0) {
                                                while ($c = $counselors->fetch_assoc()) {
                                            ?>
                                                    <div class="d-flex flex-column mb-1">
                                                        <span class="text-14 main-text"><?= htmlspecialchars($c['c_title'] . "." . $c['c_fname'] . " " . $c['c_lname']); ?></span>
                                                        <span class="text-12 sub-text" title="<?= htmlspecialchars($c['programme']); ?>"><?= htmlspecialchars(programmeShortName($c['programme'], '')); ?></span>
                                                    </div>
                                            <?php
                                                }
                                            } else {
                                                echo '<span class="text-14 main-text">N/A</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["date_registered"]; ?></span></td>

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

                                        if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAccounts($sessionUserRole) ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 4) || UserPermissions::hasPermission('students', 10)) {
                                        ?>
                                            <td class="py-3 align-middle">
                                                <div class="dropdown">
                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                    <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-details?student-id=<?= $data['sId']; ?>">
                                                                <iconify-icon icon="hugeicons:activity-01" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                Activity</a></li>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <!-- <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="downloadStudentIDCard('<?= $data['sId']; ?>')">
                                                                <iconify-icon icon="tabler:face-id" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                Download Student ID Card</a></li>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2"> -->
                                                        <?php

                                                        if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 10)) {
                                                            if (!empty($data['verification']) && empty($data['reset_time'])) {
                                                        ?>
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendStudentRegistrationEmail('<?= $data['sId']; ?>')">
                                                                        <iconify-icon icon="lets-icons:send-fill" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                        Resend Registration Email</a></li>
                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                            <?php
                                                            } else {
                                                            ?>
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendStudentRegistrationEmail('<?= $data['sId']; ?>')">
                                                                        <iconify-icon icon="solar:password-broken" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                        Resend Password Reset Email</a></li>
                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                            <?php
                                                            }
                                                            ?>
                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-student?student-id=<?= $data['sId']; ?>">
                                                                    <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                    Edit</a></li>
                                                        <?php
                                                        }

                                                        if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 4) || UserPermissions::hasPermission('students', 10)) {
                                                        ?>
                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <?php
                                                        }

                                                        ?>

                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole)  ||  SessionUser::ifUserIsAccounts($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('students', 4)) {

                                                            if ($data["status"] == "Active") {
                                                        ?>
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateStudentStatus(2,'<?= $data['sId']; ?>')">
                                                                        <iconify-icon icon="lsicon:minus-filled" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                        Deactivate</a></li>
                                                            <?php
                                                            } else {
                                                            ?>
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateStudentStatus(1,'<?= $data['sId']; ?>')">
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
                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                            <div class="row p-4 align-items-center">
                                <!-- Previous Button (col-1) -->
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentPagination('previousPage');">
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
                                            $action = $isActive ? '' : "onclick=\"handleStudentsPagination('goToPage', '{$i}')\"";
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentPagination('nextPage',<?= $number_of_pages ?>);">
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
