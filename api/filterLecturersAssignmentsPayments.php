<?php
$status = new stdClass();
require_once "../includes/connection.php";
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
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
                    $user_sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;


                    // one row per lecturer + assignment for the marked assignments
                    // payment_status => 1 Scheduled, 2 Completed, 3 Rejected, 4 Pending Approval (not scheduled yet)
                    $query = "SELECT 
            `lhsaa`.`lecturers_lecturers_id`,
            `sha`.`assignments_assignment_id`,
            COUNT(DISTINCT `lhsaa`.`lecturers_has_students_has_assignments_id`) AS marked_count,
            COUNT(DISTINCT `paid`.`lecturers_has_students_has_assignments_id`) AS paid_count,
            `lecturers`.`per_assignment_fee`,
            `users`.`user_id`, `users`.`user_fname`, `users`.`user_lname`, `users`.`profile_img`, `title`.`title`,
            `assignments`.`assignment_code`, `assignments`.`assignment`,
            `subjects`.`subject`, `levels`.`level`, `intakes`.`intake`, `programmes`.`programme`, `faculties`.`faculty`,
            MAX(`open_payment`.`assignment_payments_id`) AS assignment_payments_id,
            MAX(`open_payment`.`amount`) AS amount,
            MAX(`completed_payment`.`assignment_payments_id`) AS completed_payment_id,
            MAX(`completed_payment`.`completed_amount`) AS completed_amount,
            (CASE 
                WHEN MAX(`open_payment`.`assignment_payments_id`) IS NOT NULL THEN MAX(`open_payment`.`earning_status_earning_status_id`)
                WHEN COUNT(DISTINCT `lhsaa`.`lecturers_has_students_has_assignments_id`) > COUNT(DISTINCT `paid`.`lecturers_has_students_has_assignments_id`) THEN 4
                ELSE 2
            END) AS payment_status
        FROM `lecturers_has_students_has_assignments` AS `lhsaa`
        INNER JOIN `students_has_assignments` AS `sha` ON `lhsaa`.`students_has_assignments_students_has_assignments_id` = `sha`.`students_has_assignments_id`
        INNER JOIN `lecturers` ON `lecturers`.`lecturers_id` = `lhsaa`.`lecturers_lecturers_id`
        INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
        INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
        INNER JOIN `assignments` ON `assignments`.`assignment_id` = `sha`.`assignments_assignment_id`
        INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
        INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
        INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
        INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
        INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
        LEFT JOIN (
            SELECT `apma`.`lecturers_has_students_has_assignments_id`
            FROM `assignment_payments_has_marked_assignments` AS `apma`
            INNER JOIN `assignment_payments` AS `ap` ON `ap`.`assignment_payments_id` = `apma`.`assignment_payments_id`
            WHERE `ap`.`earning_status_earning_status_id` = '2'
            GROUP BY `apma`.`lecturers_has_students_has_assignments_id`
        ) AS `paid` ON `paid`.`lecturers_has_students_has_assignments_id` = `lhsaa`.`lecturers_has_students_has_assignments_id`
        LEFT JOIN `assignment_payments` AS `open_payment` ON (`open_payment`.`lecturers_lecturers_id` = `lhsaa`.`lecturers_lecturers_id`
            AND `open_payment`.`assignments_assignment_id` = `sha`.`assignments_assignment_id`
            AND `open_payment`.`earning_status_earning_status_id` IN ('1','3'))
        LEFT JOIN (
            SELECT `lecturers_lecturers_id`, `assignments_assignment_id`, MAX(`assignment_payments_id`) AS assignment_payments_id, SUM(`amount`) AS completed_amount
            FROM `assignment_payments`
            WHERE `earning_status_earning_status_id` = '2'
            GROUP BY `lecturers_lecturers_id`, `assignments_assignment_id`
        ) AS `completed_payment` ON (`completed_payment`.`lecturers_lecturers_id` = `lhsaa`.`lecturers_lecturers_id`
            AND `completed_payment`.`assignments_assignment_id` = `sha`.`assignments_assignment_id`)
        WHERE `lhsaa`.`submission_sts_submission_sts_id` = '2'";

                    if ($search_text != "") {
                        $search_text = Database::escape($search_text);
                        $query .= " AND (`user_fname` LIKE '%" . $search_text . "%' OR `user_lname` LIKE '%" . $search_text . "%' OR `user_email` LIKE '%" . $search_text . "%' OR `user_mobile` LIKE '%" . $search_text . "%' OR `assignment_code` LIKE '%" . $search_text . "%' OR `assignment` LIKE '%" . $search_text . "%' OR `subject` LIKE '%" . $search_text . "%')";
                    }

                    $query .= " GROUP BY `lhsaa`.`lecturers_lecturers_id`, `sha`.`assignments_assignment_id`, `lecturers`.`per_assignment_fee`, `users`.`user_id`, `users`.`user_fname`, `users`.`user_lname`, `users`.`profile_img`, `title`.`title`, `assignments`.`assignment_code`, `assignments`.`assignment`, `assignments`.`assignment_id`, `subjects`.`subject`, `levels`.`level`, `intakes`.`intake`, `programmes`.`programme`, `faculties`.`faculty`";

                    if ($user_sort != 0) {
                        $query .= " HAVING `payment_status` = '" . (int) $user_sort . "'";
                    }

                    $query .= " ORDER BY `payment_status` DESC, `assignments`.`assignment_id` DESC";

                    $results = Database::search($query);
                    $results_count = $results->num_rows;
                    $result_per_page = (int)$resultPerPage;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $payments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                        <thead>
                            <tr class="br-t-5">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                    <div class="d-flex flex-row align-items-center">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <span class="text-14 main-text fw-medium mx-2">Assignment</span>
                                    </div>
                                </th>

                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecturer Name</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Marked Assignments</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                <th scope="col" class="pe-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php
                           
                            if ($payments->num_rows > 0) {
                                while ($data = $payments->fetch_assoc()) {
                                                                            $status_classes = [
                                                                                "1" => "table-btn-yellow",
                                                                                "2" => "table-btn-success",
                                                                                "3" => "table-btn-danger",
                                                                                "4" => "table-btn-pink",
                                                                            ];
                                                                            $status_names = [
                                                                                "1" => "Scheduled",
                                                                                "2" => "Completed",
                                                                                "3" => "Rejected",
                                                                                "4" => "Pending Approval",
                                                                            ];
                                                                            $unpaidCount = $data['marked_count'] - $data['paid_count'];
                                                                            if ($data['payment_status'] == 4) {
                                                                                $amount = $unpaidCount * $data['per_assignment_fee'];
                                                                            } else if ($data['payment_status'] == 2) {
                                                                                $amount = $data['completed_amount'];
                                                                            } else {
                                                                                $amount = $data['amount'];
                                                                            }
                                                                            $viewPaymentId = !empty($data['assignment_payments_id']) ? $data['assignment_payments_id'] : $data['completed_payment_id'];
                                                                    ?>
                                                                            <tr>
                                                                                <td class="px-4 pe-0 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center p-0 g-0 m-0">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                        <p class="text-14 main-text mx-2"><?= $data['assignment_code'] . " - " . $data['assignment'] ?></br><span class="sub-text text-12"><?= $data['faculty'] . "/" . $data['programme'] . "/" . $data['intake'] . "/" . $data['level'] . "/" . $data['subject'] ?></span></p>
                                                                                    </div>
                                                                                </td>
                                                                                <td colspan="3" class="py-2 align-middle">
                                                                                    <div class="flex-grow-1 d-flex align-items-center">
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

                                                                                <td colspan="3" class="py-2 align-middle">
                                                                                    <span class="text-14 main-text"><?= $data['marked_count'] ?> Marked / <?= $data['paid_count'] ?> Paid</span></br>
                                                                                    <span class="text-12 sub-text">LKR <?= number_format($data['per_assignment_fee'], 2) ?> per assignment</span>
                                                                                </td>

                                                                                <td colspan="2" class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($amount, 2); ?></span></td>
                                                                                <td class="py-2 align-middle">
                                                                                    <button class="table-btn <?php echo $status_classes[$data['payment_status']]; ?> w-100"> <?php echo $status_names[$data['payment_status']]; ?></button>
                                                                                </td>
                                                                                <?php

                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::validPermisisonType('payments')) {
                                                                                ?>
                                                                                    <td class="py-3 pe-4 align-middle">
                                                                                        <div class="dropdown">
                                                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                <?php
                                                                                                if (!empty($viewPaymentId)) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="lecturer-assignment-payment?payment-id=<?php echo $viewPaymentId; ?>">
                                                                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            View</a></li>
                                                                                                <?php
                                                                                                }
                                                                                                if ($data['payment_status'] == 4) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer" onclick="addLecturerAssignmentPayment('<?= $data['lecturers_lecturers_id'] ?>', '<?= $data['assignments_assignment_id'] ?>')">
                                                                                                            <iconify-icon icon="mdi:approve" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Schedule Payment</a></li>
                                                                                                <?php
                                                                                                } else if ($data['payment_status'] == 1) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer" onclick="addLecturerAssignmentPayment('<?= $data['lecturers_lecturers_id'] ?>', '<?= $data['assignments_assignment_id'] ?>')">
                                                                                                            <iconify-icon icon="mdi:refresh" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Re-calculate Payment</a></li>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer" onclick="updateLecturerAssignmentPayment(<?= $data['assignment_payments_id'] ?>,3)">
                                                                                                            <iconify-icon icon="zondicons:close-outline" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Reject Payment</a></li>
                                                                                                <?php
                                                                                                } else if ($data['payment_status'] == 3) {
                                                                                                ?>
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer" onclick="addLecturerAssignmentPayment('<?= $data['lecturers_lecturers_id'] ?>', '<?= $data['assignments_assignment_id'] ?>')">
                                                                                                            <iconify-icon icon="mdi:calendar-refresh" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Re-schedule Payment</a></li>
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
                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme" id="pagination-container">
                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                            <div class="row p-4 align-items-center">
                                <!-- Previous Button (col-1) -->
                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLecturersAssignmentsPaymentsPagination('previousPage')">
                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    <span class="text-14 main-text fw-semibold">Previous</span>
                                </button>
                                <!-- Page Numbers (col-10, centered) -->
                                <div class="col-10 custom-pagination d-flex justify-content-center">
                                    <ul class="pagination-list d-flex mb-0">
                                        <?php
                                        $shudderPages1 = false;
                                        $shudderPages2 = false;

                                        function renderPageButton($i, $isActive = false)
                                        {
                                            $activeClass = $isActive ? 'active' : '';
                                            $action = $isActive ? '' : "onclick=\"handleLecturersAssignmentsPaymentsPagination('goToPage', '{$i}')\"";
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
                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersAssignmentsPaymentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
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