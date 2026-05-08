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

                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $user_sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;


                    $query = "SELECT * FROM `lecturer_payments` 
                    INNER JOIN `lecturers` ON `lecturers`.`lecturers_id` = `lecturer_payments`.`lecturers_lecturers_id`
                    INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
                    INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
                    INNER JOIN `time_table` ON `time_table`.`time_table_id` = `lecturer_payments`.`time_table_time_table_id`
                    INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                    INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                    INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                    INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                    INNER JOIN `earning_status` ON `earning_status`.`earning_status_id` = `lecturer_payments`.`earning_status_earning_status_id`
                    
                    ";

                    if ($search_text != "") {
                        $query .= " WHERE (`user_fname` LIKE '%" . $search_text . "%' OR `user_lname` LIKE '%" . $search_text . "%' OR `user_email` LIKE '%" . $search_text . "%' OR `user_mobile` LIKE '%" . $search_text . "%')";
                    }

                    if ($user_sort != 0) {
                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE `lecturer_payments`.`earning_status_earning_status_id` = '" . $user_sort . "'";
                        } else {
                            $query .= " AND `lecturer_payments`.`earning_status_earning_status_id` = '" . $user_sort . "'";
                        }
                    }

                    $query .= " ORDER BY `lecturer_payments`.`lecturer_payments_id` DESC";

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
                                        <span class="text-14 main-text fw-medium mx-2">Lecture</span>
                                    </div>
                                </th>

                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecturer Name</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Request Officer</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Date Time</th>
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
                            ?>
                                    <tr>
                                        <td class="px-4 pe-0 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                <p class="text-14 main-text mx-2"><?= $data['faculty'] . "/" . $data['programme'] . "/" . $data['intake'] . "/" . $data['level'] . "/" . $data['subject'] ?></p>
                                            </div>
                                        </td>
                                        <td colspan="3" class="py-2 align-middle">
                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['user_id']; ?>)">
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
                                            <div class="d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['user_id']; ?>)">
                                                <?php
                                                $requestUser = Database::search("SELECT * FROM `lecturer_payments_logs`
                                                                                    INNER JOIN `users` ON `lecturer_payments_logs`.`users_user_id` = `users`.`user_id`
                                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id` 
                                                                                    WHERE `lecturer_payments_lecturer_payments_id`='" . $data['lecturer_payments_id'] . "' ORDER BY `lecturer_payments_logs`.`lecturer_payments_logs_id` ASC LIMIT 1");
                                                if ($requestUser->num_rows > 0) {
                                                    $requestUserData = $requestUser->fetch_assoc();
                                                    if (!empty($requestUserData["profile_img"]) && file_exists('./assets/imgs/users/' . $requestUserData["profile_img"])) {
                                                ?>
                                                        <img src="./assets/imgs/users/<?php echo $requestUserData["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                    <?php
                                                    } else {
                                                        $firstNameInitial = strtoupper(substr($requestUserData["user_fname"], 0, 1));
                                                        $lastNameInitial = strtoupper(substr($requestUserData["user_lname"], 0, 1));
                                                    ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                    <?php
                                                    }
                                                    ?>
                                                    <span class="text-14 main-text mx-2"><?php echo $requestUserData["title"] . "." . $requestUserData["user_fname"] . " " . $requestUserData["user_lname"]; ?></span>
                                                <?php
                                                } else {
                                                ?>
                                                    <span class="text-14 main-text mx-2">N/A</span>
                                                <?php
                                                }
                                                ?>
                                            </div>

                                        </td>
                                        <td colspan="2" class="py-2 align-middle"><span class="text-14 main-text"><?= $data["date"]; ?></span></td>
                                        <td colspan="2" class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $data["amount"]; ?></span></td>
                                        <td class="py-2 align-middle">
                                            <?php
                                            $status_classes = [
                                                "1"     => "table-btn-yellow",
                                                "2"  => "table-btn-success",
                                                "3"  => "table-btn-danger",
                                            ];
                                            $sbutton_class = $status_classes[$data["earning_status_id"]];
                                            ?>
                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["earning_status"]; ?></button>
                                        </td>
                                        <?php

                                        if (SessionUser::ifUserIsPro($sessionUserRole) || $sessionUserRole == "Manager") {
                                        ?>
                                            <td class="py-3 pe-4 align-middle">
                                                <div class="dropdown">
                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                    <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="lecturer-lecture-payment?lecturer-payment-id=<?php echo $data['lecturer_payments_id']; ?>">
                                                                <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                View</a></li>
                                                        <?php
                                                        if ($data['earning_status_earning_status_id'] == 1) {
                                                        ?>
                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick="updateLecturerLecturePayment(<?= $data['lecturer_payments_id'] ?>,2)">
                                                                    <iconify-icon icon="mdi:approve" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                    Approve</a></li>
                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick="updateLecturerLecturePayment(<?= $data['lecturer_payments_id'] ?>,3)">
                                                                    <iconify-icon icon="zondicons:close-outline" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                    Reject</a></li>
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
                                            <img src="./assets/imgs/common/no-data.png" class="w-25 h-auto" alt="No data" class="empty-icon" />
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
                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLecturersLecturePaymentsPagination('previousPage')">
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
                                            $action = $isActive ? '' : "onclick=\"handleLecturersLecturePaymentsPagination('goToPage', '{$i}')\"";
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
                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersLecturePaymentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
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