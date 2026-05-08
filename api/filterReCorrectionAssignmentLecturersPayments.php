<?php
$status = new stdClass();
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
                    require_once "../includes/connection.php";

                    $id = isset($decoded['id']) ? $decoded['id'] : null;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $query = "
                    SELECT 
                        `ar`.`lecturers_lecturers_id` AS `lecturers_id`,
                        COUNT(*) AS `total_count`,
                        COUNT(CASE WHEN `ar`.`submission_sts_submission_sts_id` = 1 THEN 1 END) AS `pending_count`,
                        COUNT(CASE WHEN `ar`.`submission_sts_submission_sts_id` = 2 THEN 1 END) AS `marked_count`,
                        `users`.`user_fname`,
                        `users`.`user_lname`,
                        `title`.`title`,
                        `users`.`profile_img`,
                        `lecturers`.`per_assignment_fee`
                    FROM 
                        `assignment_recorrection` AS `ar`
                    INNER JOIN 
                        `lecturers` ON `ar`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                    INNER JOIN 
                        `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                    INNER JOIN 
                        `title` ON `users`.`title_title_id` = `title`.`title_id`
                    INNER JOIN 
                        `students_has_assignments` AS `sha` ON `ar`.`students_has_assignments_students_has_assignments_id` = `sha`.`students_has_assignments_id`
                    WHERE 
                        `sha`.`assignments_assignment_id` = '" . $id . "'
                 
                    ";

                    if ($search_text != "") {
                        $query .= " AND (`user_fname` LIKE '%" . $search_text . "%' OR `user_lname` LIKE '%" . $search_text . "%' OR `user_email` LIKE '%" . $search_text . "%' OR `user_mobile` LIKE '%" . $search_text . "%')";
                    }

                    $query .= "GROUP BY 
                    `ar`.`lecturers_lecturers_id`";

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
                                        <span class="text-14 main-text fw-medium mx-2">Lecturer</span>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Assign Count</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Marked Count</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Pending Count</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                <th scope="col" class="py-3 bg-table-header">
                                    <div class="d-flex flex-row align-items-center">
                                        <span class="text-14 main-text fw-medium">Status</span>
                                        <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>

                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php

                            if ($payments->num_rows > 0) {
                                while ($data = $payments->fetch_assoc()) {
                                    $amount = $data["marked_count"] * $data["per_assignment_fee"];
                            ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
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
                                                    <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["total_count"]; ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["marked_count"]; ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["pending_count"]; ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $amount; ?></span></td>
                                        <td class="py-2 align-middle">
                                            <?php
                                            $status_classes = [
                                                "Pending"  => "table-btn-yellow",
                                                "Pending Approval"     => "table-btn-pink",
                                                "Approval"     => "table-btn-success",
                                                "Rejected"  => "table-btn-danger",
                                            ];
                                            $status = "Pending";
                                            $sbutton_class = $status_classes[$status];
                                            $lectureAssignmentPayment = Database::search("SELECT * FROM `assignment_payments` WHERE `lecturers_lecturers_id` = '" . $data["lecturers_id"] . "' AND `assignments_assignment_id` = '" . $id . "'");
                                            if ($lectureAssignmentPayment->num_rows > 0) {
                                                $payment = $lectureAssignmentPayment->fetch_assoc();

                                                if ($payment["earning_status_earning_status_id"] == 1) {
                                                    $sbutton_class = $status_classes['Pending Approval'];
                                                    $status = "Pending Approval";
                                                } else if ($payment["earning_status_earning_status_id"] == 2) {
                                                    $sbutton_class = $status_classes['Approval'];
                                                    $status = "Approval";
                                                } else if ($payment["earning_status_earning_status_id"] == 3) {
                                                    $sbutton_class = $status_classes['Rejected'];
                                                    $status = "Rejected";
                                                }
                                            }
                                            ?>
                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $status; ?></button>
                                        </td>
                                        <td class="py-3 align-middle">
                                            <?php
                                            if ($data["total_count"] == $data["marked_count"]) {
                                                if ($lectureAssignmentPayment->num_rows == 0) {
                                            ?>
                                                    <button class="table-btn table-btn-success active w-75" onclick="addLecturerAssignmentPayment('<?php echo $data['lecturers_id']; ?>', '<?php echo $id; ?>')">Confirm Payment</button>
                                            <?php
                                                }
                                            }
                                            ?>
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
                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme" id="pagination-container">
                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                            <div class="row p-4 align-items-center">
                                <!-- Previous Button (col-1) -->
                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleAssignmentLecturersPayments('<?= $id ?>','previousPage')">
                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    <span class="text-14 main-text fw-semibold">Previous</span>
                                </button>
                                <!-- Page Numbers (col-10, centered) -->
                                <div class="col-10 custom-pagination d-flex justify-content-center">
                                    <ul class="pagination-list d-flex mb-0">
                                        <?php
                                        $shudderPages1 = false;
                                        $shudderPages2 = false;

                                        function renderPageButton($i, $isActive = false, $id)
                                        {
                                            $activeClass = $isActive ? 'active' : '';
                                            $action = $isActive ? '' : "onclick=\"handleAssignmentLecturersPayments('{$id}','goToPage', '{$i}')\"";
                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                        }

                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                renderPageButton($i, $pageno == $i, $id);
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
                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleAssignmentLecturersPayments('<?= $id ?>','nextPage',null,'<?= $number_of_pages ?>')">
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