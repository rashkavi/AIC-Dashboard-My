<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole == "Developer") {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    require_once "../includes/connection.php";

                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;

                    $update = isset($decoded['update']) ? $decoded['update'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $selectPayments = isset($decoded['selectPayments']) ? $decoded['selectPayments'] : [];
                    $unSelectPayments = isset($decoded['unSelectPayments']) ? $decoded['unSelectPayments'] : [];

                    $query = "SELECT `students`.`student_id` AS 'sId', `students`.`first_name`, `students`.`last_name`, `students`.`profile_img`, `title`.`title`,`date_paid`,`from`,`to`,`portal_payment_id`,`portal_payment_status_id`,`portal_payment_status`
                                                                            FROM `portal_payment`
                                                                            INNER JOIN `students` ON `portal_payment`.`students_student_id`=`students`.`student_id` 
                                                                            INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                                                                            INNER JOIN `portal_payment_status` ON `portal_payment`.`portal_payment_status_portal_payment_status_id`=`portal_payment_status`.`portal_payment_status_id`
                                                                            ";

                    if ($search_text != "") {
                        $query .= " WHERE (`student_id` LIKE '%" . $search_text . "%' OR `first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%')";
                    }

                    if ($sort != 0) {
                        if ($sort == 1) {
                            $query .= " ORDER BY `first_name` ASC";
                        } else if ($sort == 2) {
                            $query .= " ORDER BY `first_name` DESC";
                        } else if ($sort == 3) {
                            $query .= " ORDER BY `portal_payment`.`date_paid` DESC";
                        } else if ($sort == 4) {
                            $query .= " ORDER BY `portal_payment`.`date_paid` ASC";
                        } else if ($sort == 5) {
                            if (strpos($query, 'WHERE') == false) {
                                $query .= " WHERE `portal_payment_status`.`portal_payment_status_id` = '1'";
                            } else {
                                $query .= " AND `portal_payment_status`.`portal_payment_status_id` = '1'";
                            }
                        } else if ($sort == 6) {
                            if (strpos($query, 'WHERE') == false) {
                                $query .= " WHERE `portal_payment_status`.`portal_payment_status_id` = '2'";
                            } else {
                                $query .= " AND `portal_payment_status`.`portal_payment_status_id` = '2'";
                            }
                        } else if ($sort == 7) {
                            if (strpos($query, 'WHERE') == false) {
                                $query .= " WHERE `portal_payment_status`.`portal_payment_status_id` = '3'";
                            } else {
                                $query .= " AND `portal_payment_status`.`portal_payment_status_id` = '3'";
                            }
                        }
                    }

                    if ($sort == 0 || $sort > 4) {
                        $query .= " ORDER BY `date_paid` DESC";
                    }

                    $isUpdatePaymentStatus = $update == 1 ? true : ($update == 2 ? true : false);

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
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Paid</th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">From</th>
                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">To</th>
                                <th scope="col" class="px-4 py-3 bg-table-header">
                                    <div class="d-flex flex-row align-items-center">
                                        <span class="text-14 main-text fw-medium">Status</span>
                                        <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    </div>
                                </th>

                                <?php
                                if ($sessionUserRole == "Developer") {
                                ?>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>
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
                                        <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <?php
                                                $isMatchSelectUpdate = false;
                                                if ($isUpdatePaymentStatus) {
                                                    if (count($selectPayments) > 0) {
                                                        if (in_array($data['portal_payment_id'], $selectPayments)) {
                                                            if ($data['portal_payment_status_id'] != 3  && $update == 3) {
                                                                $isMatchSelectUpdate = true;
                                                            } else if ($data['portal_payment_status_id'] != 2 && $update == 2) {
                                                                $isMatchSelectUpdate = true;
                                                            }
                                                        }
                                                    }

                                                    if (count($unSelectPayments) > 0) {
                                                        if (in_array($data['portal_payment_id'], $unSelectPayments)) {
                                                            $isMatchSelectUpdate = false;
                                                        }
                                                    }

                                                    if (count($selectPayments) == 0 && count($unSelectPayments) == 0) {
                                                        if ($data['portal_payment_status_id'] != 3 && $update == 3) {
                                                            $isMatchSelectUpdate = true;
                                                        } else if ($data['portal_payment_status_id'] != 2 && $update == 2) {
                                                            $isMatchSelectUpdate = true;
                                                        }
                                                    }
                                                }

                                                if ($isMatchSelectUpdate) {
                                                ?>
                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" checked id="check-user<?= $data["portal_payment_id"]; ?>" onchange="checkRowEvents(<?= $data['portal_payment_id']; ?>);">
                                                <?php
                                                } else {
                                                ?>
                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["portal_payment_id"]; ?>" onchange="checkRowEvents(<?= $data['portal_payment_id']; ?>);">
                                                <?php
                                                }
                                                ?>
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?= $data['portal_payment_id']; ?>)">
                                                    <?php
                                                    if (!empty($data["profile_img"]) && file_exists('../assets/imgs/students/' . $data["profile_img"])) {
                                                    ?>
                                                        <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
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
                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["sId"]; ?></span></td>
                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["date_paid"]; ?></span></td>
                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["from"]; ?></span></td>
                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["to"]; ?></span></td>

                                        <td class="px-4 py-2 align-middle">
                                            <?php
                                            $status_classes = [
                                                "1"     => "table-btn-yellow",
                                                "2"  => "table-btn-success",
                                                "3"  => "table-btn-danger",
                                            ];
                                            $sbutton_class = $status_classes[$data["portal_payment_status_id"]];
                                            ?>
                                            <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["portal_payment_status"]; ?></button>
                                        </td>

                                        <?php

                                        if ($sessionUserRole == "Developer") {

                                            if ($data["portal_payment_status_id"] == 1) {
                                        ?>
                                                <td class="px-4 py-3 align-middle">
                                                    <div class="dropdown">
                                                        <button class="table-btn table-btn-dark-theme w-100" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false">Update Status</button>
                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center p-2" onclick="updateStudentPortalPaymentStatus(<?= $data['portal_payment_id'] ?>,2)">
                                                                    <iconify-icon icon="gg:check-o" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                    Accept</a></li>

                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center p-2" onclick="updateStudentPortalPaymentStatus(<?= $data['portal_payment_id'] ?>,3)">
                                                                    <iconify-icon icon="gg:close-o" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                    Reject</a></li>
                                                        </ul>
                                                    </div>
                                                </td>
                                                <?php
                                            } else {
                                                if ($data["portal_payment_status_id"] == 2) {
                                                ?>
                                                    <td class="px-4 py-3 align-middle">
                                                        <button class="table-btn table-btn-danger active w-100" onclick="updateStudentPortalPaymentStatus(<?= $data['portal_payment_id'] ?>,3)">Reject Payment</button>
                                                    </td>
                                                <?php
                                                } else {
                                                ?>
                                                    <td class="px-4 py-3 align-middle">
                                                        <button class="table-btn table-btn-success active w-100" onclick="updateStudentPortalPaymentStatus(<?= $data['portal_payment_id'] ?>,2)">Accept Payment</button>
                                                    </td>
                                        <?php
                                                }
                                            }
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handlePortalPaymentStudentsPagination('previousPage');">
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
                                            $action = $isActive ? '' : "onclick=\"handlePortalPaymentStudentsPagination('goToPage', '{$i}')\"";
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handlePortalPaymentStudentsPagination('nextPage',<?= $number_of_pages ?>);">
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
