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

                    $filters = [
                        'text'       => isset($decoded['text']) ? $decoded['text'] : '',
                        'branch'     => isset($decoded['branch']) ? $decoded['branch'] : 0,
                        'faculty'    => isset($decoded['faculty']) ? $decoded['faculty'] : 0,
                        'programme'  => isset($decoded['programme']) ? $decoded['programme'] : 0,
                        'intake'     => isset($decoded['intake']) ? $decoded['intake'] : 0,
                        'sortByName' => isset($decoded['sortByName']) ? (int)$decoded['sortByName'] : 0,
                    ];

                    $resultPerPage = isset($decoded['resultPerPage']) ? (int)$decoded['resultPerPage'] : 10;
                    $pageno        = isset($decoded['page']) ? (int)$decoded['page'] : 1;
                    if ($pageno < 1) $pageno = 1;
                    if ($resultPerPage < 1) $resultPerPage = 10;

                    // Bucketed lead_student_status_id groups — must stay
                    // identical to performance.php so the two never disagree.
                    // 1 Pending Action : 1,6,7,11   2 Rejected : 2,9,12
                    // 3 In Progress    : 3,4,5,8,10,13
                    // 4 Ready to Enroll: 14,15,16   5 Enrolled : 17
                    $PENDING_IDS    = "1,6,7,11";
                    $REJECTED_IDS   = "2,9,12";
                    $INPROGRESS_IDS = "3,4,5,8,10,13";
                    $READY_IDS      = "14,15,16";
                    $ENROLLED_IDS   = "17";

                    /* ── Branch/Faculty/Programme/Intake describe the
                           STUDENT, not the counsellor, so this is applied
                           inside the LEFT JOIN's ON clause via EXISTS
                           (never a real JOIN — a real join would fan out
                           one row per students_has_intakes record and
                           inflate every COUNT/SUM(CASE...) bucket for
                           students enrolled in more than one intake).
                           Same intake > programme > faculty priority as
                           the due report's cascading filter. ────────── */
                    $studentFilterSql = "";
                    if (!empty($filters['intake']) && $filters['intake'] != '0') {
                        $studentFilterSql = " AND EXISTS (
                            SELECT 1 FROM students_has_intakes shi
                            WHERE shi.students_student_id = students.student_id
                            AND shi.intakes_intake_id = '" . (int)$filters['intake'] . "'
                        ) ";
                    } elseif (!empty($filters['programme']) && $filters['programme'] != '0') {
                        $studentFilterSql = " AND EXISTS (
                            SELECT 1 FROM students_has_intakes shi
                            INNER JOIN intakes intk ON shi.intakes_intake_id = intk.intake_id
                            WHERE shi.students_student_id = students.student_id
                            AND intk.programmes_programme_id = '" . (int)$filters['programme'] . "'
                        ) ";
                    } elseif (!empty($filters['faculty']) && $filters['faculty'] != '0') {
                        $studentFilterSql = " AND EXISTS (
                            SELECT 1 FROM students_has_intakes shi
                            INNER JOIN intakes intk ON shi.intakes_intake_id = intk.intake_id
                            INNER JOIN programmes prog ON intk.programmes_programme_id = prog.programme_id
                            WHERE shi.students_student_id = students.student_id
                            AND prog.faculties_faculty_id = '" . (int)$filters['faculty'] . "'
                        ) ";
                    } elseif (!empty($filters['branch']) && $filters['branch'] != '0') {
                        $studentFilterSql = " AND EXISTS (
                            SELECT 1 FROM students_has_intakes shi
                            INNER JOIN intakes intk ON shi.intakes_intake_id = intk.intake_id
                            INNER JOIN programmes prog ON intk.programmes_programme_id = prog.programme_id
                            INNER JOIN faculties fac ON prog.faculties_faculty_id = fac.faculty_id
                            WHERE shi.students_student_id = students.student_id
                            AND fac.branches_branch_id = '" . (int)$filters['branch'] . "'
                        ) ";
                    }

                    $orderBy = " ORDER BY `users`.`user_reg_date` DESC ";
                    if ($filters['sortByName'] == 1) {
                        $orderBy = " ORDER BY `users`.`user_fname` ASC, `users`.`user_lname` ASC ";
                    } elseif ($filters['sortByName'] == 2) {
                        $orderBy = " ORDER BY `users`.`user_fname` DESC, `users`.`user_lname` DESC ";
                    }

                    $whereSql = " WHERE `user_roles`.`user_role_id` IN (3,9) AND `status`.`status_id` = 1 ";
                    if (!empty($filters['text'])) {
                        $t = Database::escape($filters['text']);
                        $whereSql .= " AND (
                            `users`.`user_fname` LIKE '%$t%'
                            OR `users`.`user_lname` LIKE '%$t%'
                            OR `users`.`user_email` LIKE '%$t%'
                            OR `users`.`user_mobile` LIKE '%$t%'
                        ) ";
                    }

                    $query = "SELECT 
            `users`.`user_id`, 
            `users`.`user_fname`, 
            `users`.`user_lname`, 
            `users`.`user_email`, 
            `users`.`user_mobile`, 
            `users`.`user_reg_date`,
            `users`.`user_verification`,
            `user_roles`.`user_role`, 
            `user_roles`.`user_role_id`, 
            `title`.`title`, 
            `status`.`status`,
            `status`.`status_id`,
            `users`.`profile_img`,

            COUNT(`students_has_intakes`.`students_has_intakes_id`) AS total_students,

            SUM(CASE 
                WHEN `students_has_intakes`.`lead_student_status_lead_student_status_id` IN ($PENDING_IDS) 
                THEN 1 ELSE 0 
            END) AS pending_students,

            SUM(CASE 
                WHEN `students_has_intakes`.`lead_student_status_lead_student_status_id` IN ($REJECTED_IDS) 
                THEN 1 ELSE 0 
            END) AS rejected_students,

            SUM(CASE 
                WHEN `students_has_intakes`.`lead_student_status_lead_student_status_id` IN ($INPROGRESS_IDS) 
                THEN 1 ELSE 0 
            END) AS inprogress_students,

            SUM(CASE 
                WHEN `students_has_intakes`.`lead_student_status_lead_student_status_id` IN ($READY_IDS) 
                THEN 1 ELSE 0 
            END) AS ready_students,

            SUM(CASE 
                WHEN `students_has_intakes`.`lead_student_status_lead_student_status_id` IN ($ENROLLED_IDS) 
                THEN 1 ELSE 0 
            END) AS enrolled_students

        FROM `users` 

        INNER JOIN `user_roles` 
            ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id` 

        INNER JOIN `title` 
            ON `users`.`title_title_id` = `title`.`title_id` 

        INNER JOIN `status` 
            ON `users`.`status_status_id` = `status`.`status_id`

        LEFT JOIN `students_has_intakes` 
            ON `users`.`user_id` = `students_has_intakes`.`counselor_user_id`
            AND `students_has_intakes`.`lead_student_status_lead_student_status_id` IS NOT NULL

      

                        $studentFilterSql

                    $whereSql

                    GROUP BY `users`.`user_id`

                    $orderBy";

                    $results = Database::search($query);
                    $results_count = $results ? $results->num_rows : 0;

                    $number_of_pages = ($results_count > $resultPerPage) ? ceil($results_count / $resultPerPage) : 1;
                    if ($pageno > $number_of_pages) $pageno = $number_of_pages;

                    $page_results = ($pageno - 1) * $resultPerPage;
                    $users = Database::search($query . " LIMIT " . (int)$resultPerPage . " OFFSET " . (int)$page_results);
?>
                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                        <thead>
                            <tr class="br-t-5 align-content-center">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                    <div class="d-flex flex-row align-items-center cursor-pointer">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handlePerformancePagination('sortByName')">
                                            <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                            <?php if ($filters['sortByName'] == 0): ?>
                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                            <?php else: ?>
                                                <iconify-icon id="sort-icon" icon="<?= $filters['sortByName'] == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Pending Action</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Rejected</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">In Progress</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Ready to Enroll</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Enrolled</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Total</th>
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
                            <?php if ($users && $users->num_rows > 0): ?>
                                <?php while ($data = $users->fetch_assoc()):
                                    $reportUserName = $data["title"] . ". " . $data["user_fname"] . " " . $data["user_lname"];
                                ?>
                                    <tr>
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['user_id']; ?>)">
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
                                            </div>
                                        </td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["pending_students"]); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["rejected_students"]); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["inprogress_students"]); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["ready_students"]); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["enrolled_students"]); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($data["total_students"]); ?></span></td>

                                        <td class="py-3 align-middle">
                                            <div class="dropdown">
                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="counselor-performance?user-id=<?= $data['user_id']; ?>">
                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                            View</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr class="justify-content-center align-middle">
                                    <td colspan="13" class="align-middle justify-content-center">
                                        <div class="d-flex flex-column align-items-center">
                                            <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                            <div class="row p-4 align-items-center">
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handlePerformancePagination('previousPage')">
                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    <span class="text-14 main-text fw-semibold d-none d-md-block">Previous</span>
                                </button>
                                <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                    <ul class="pagination-list d-flex mb-0">
                                        <?php
                                        $shudderPages1 = false;
                                        $shudderPages2 = false;

                                        function renderPageButton($i, $isActive = false)
                                        {
                                            $activeClass = $isActive ? 'active' : '';
                                            $action = $isActive ? '' : "onclick=\"handlePerformancePagination('goToPage', '{$i}')\"";
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handlePerformancePagination('nextPage',null,'<?= $number_of_pages ?>')">
                                    <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
                                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                </button>
                            </div>
                        </nav>
                    </div>
                    <span class="d-none" id="total-count-marker" data-count="<?= $results_count ?>"></span>
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
