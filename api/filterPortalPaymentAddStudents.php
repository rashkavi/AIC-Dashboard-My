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

                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $selectStudents = isset($decoded['selectStudents']) ? $decoded['selectStudents'] : [];
                    $unSelectStudents = isset($decoded['unSelectStudents']) ? $decoded['unSelectStudents'] : [];

                    $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                    $query = "SELECT `students`.`student_id` as 'sId',  `login_details`.`first_name`, `login_details`.`last_name`, `login_details`.`email`, `students`.`mobile`,`students`.`profile_img`, `title`.`title`,`status`.`status_id`, `status`.`status`
                                            FROM `students`  
                                           INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                                            INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                                            INNER JOIN `status` ON `login_details`.`status_status_id`=`status`.`status_id`";

                    if ($search_text != "") {
                        $query .= " WHERE (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%')";
                    }

                    if ($sort != 0) {
                        if ($sort == 1) {
                            $query .= " ORDER BY `first_name` ASC";
                        } else if ($sort == 2) {
                            $query .= " ORDER BY `first_name` DESC";
                        } else if ($sort == 3) {
                            if (strpos($query, 'WHERE') == false) {
                                $query .= " WHERE `login_details`.`status_status_id` = 1";
                            } else {
                                $query .= " AND `login_details`.`status_status_id` = 1";
                            }
                        } else if ($sort == 4) {
                            if (strpos($query, 'WHERE') == false) {
                                $query .= " WHERE `login_details`.`status_status_id` = 2";
                            } else {
                                $query .= " AND `login_details`.`status_status_id` = 2";
                            }
                        } else if ($sort == 5) {
                            $query .= " ORDER BY `student_id` DESC";
                        } else if ($sort == 6) {
                            $query .= " ORDER BY `student_id` ASC";
                        } else if ($sort == 7) {
                            if (strpos($query, 'WHERE') == false) {
                                $query .= " WHERE `student_id` IN (SELECT `students_student_id` FROM `portal_payment` 
                                WHERE `portal_payment`.`portal_payment_status_portal_payment_status_id` = 2 AND `to` >= '" . $dateTime->format("Y-m-d H:i:s") . "'
                                )";
                            } else {
                                $query .= " AND `student_id` IN (SELECT `students_student_id` FROM `portal_payment` 
                            WHERE `portal_payment`.`portal_payment_status_portal_payment_status_id` = 2 AND `to` >= '" . $dateTime->format("Y-m-d H:i:s") . "'
                            )";
                            }
                        } else if ($sort == 8) {
                            if (strpos($query, 'WHERE') == false) {
                                $query .= " WHERE `student_id` NOT IN (SELECT `students_student_id` FROM `portal_payment` 
                                WHERE `portal_payment`.`portal_payment_status_portal_payment_status_id` = 2 AND `to` >= '" . $dateTime->format("Y-m-d H:i:s") . "'
                                )";
                            } else {
                                $query .= " AND `student_id` NOT IN (SELECT `students_student_id` FROM `portal_payment` 
                            WHERE `portal_payment`.`portal_payment_status_portal_payment_status_id` = 2 AND `to` >= '" . $dateTime->format("Y-m-d H:i:s") . "'
                            )";
                            }
                        }
                    }

                    if ($sort == 0 || $sort == 3 || $sort == 4) {
                        $query .= " ORDER BY `student_id` DESC";
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
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                    <div class="d-flex flex-row align-items-center">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                    </div>
                                </th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Email</th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Access End Date</th>
                                <th scope="col" class="px-4 py-3 bg-table-header">
                                    <div class="d-flex flex-row align-items-center">
                                        <span class="text-14 main-text fw-medium">Portal Payment Status</span>
                                        <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    </div>
                                </th>

                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>

                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php
                            if ($students->num_rows > 0) {
                                while ($data = $students->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['sId']; ?>)">
                                                    <?php
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
                                            </div>
                                        </td>
                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["sId"]; ?></span></td>
                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>

                                        <?php
                                        $portalAccess = Database::search("SELECT * FROM `portal_payment` 
                                                                        WHERE `students_student_id` = '" . $data["sId"] . "' 
                                                                        AND `portal_payment_status_portal_payment_status_id` = '2' 
                                                                        ORDER BY `to` DESC LIMIT 1");

                                        if ($portalAccess->num_rows > 0) {
                                            $portalAccessData = $portalAccess->fetch_assoc();

                                        ?>
                                            <td class="px-4 py-2 align-middle">
                                                <span class="text-14 main-text"><?= $portalAccessData["to"]; ?></span>
                                            </td>
                                        <?php
                                        } else {
                                        ?>
                                            <td class="px-4 py-2 align-middle"></td>
                                        <?php
                                        }
                                        ?>

                                        <td class="px-4 py-2 align-middle">
                                            <?php
                                            $portalAccess = Database::search("SELECT * FROM `portal_payment` 
                                                                        WHERE `students_student_id` = '" . $data["sId"] . "' 
                                                                        AND `portal_payment_status_portal_payment_status_id` = '2' 
                                                                        AND `to` >= '" . $dateTime->format("Y-m-d H:i:s") . "'
                                                                        ORDER BY `to` DESC LIMIT 1");

                                            if ($portalAccess->num_rows > 0) {
                                                $portalAccessData = $portalAccess->fetch_assoc();
                                            ?>
                                                <button class="table-btn table-btn-success w-100">Access Granted</button>
                                            <?php
                                            } else {
                                            ?>
                                                <button class="table-btn table-btn-danger w-100">Access Denied</button>
                                            <?php
                                            }
                                            ?>
                                        </td>
                                        <td class="px-4 pt-3 pb-3 align-middle">
                                            <button class="table-btn table-btn-dark-theme w-100" onclick="addPortalPayment(<?= $data['sId'] ?>)">Activate</button>
                                        </td>

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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handlePortalPaymentAddStudentPagination('previousPage');">
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
                                            $action = $isActive ? '' : "onclick=\"handlePortalPaymnetAddStudentsPagination('goToPage', '{$i}')\"";
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handlePortalPaymentAddStudentPagination('nextPage',<?= $number_of_pages ?>);">
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
