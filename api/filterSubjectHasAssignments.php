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

                    $subject = isset($decoded['subject_id']) ? $decoded['subject_id'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    if (empty($subject) || $subject == 0) {
                        $status->error = 'Invalid Subejct Id';
                    } else {
                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                        $validLecturer = false;
                        $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $subject . "'");
                        if ($lecturerSubject->num_rows > 0) {
                            $validLecturer = true;
                        }

                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('assignments', $subject) || $validLecturer)) {
                            $status->error = 'You are not authorized to filter lectures.';
                        } else {

                            $query = "SELECT * FROM `assignments`
                    INNER JOIN `assignment_status` ON `assignments`.`assignment_status_assignment_status_id` = `assignment_status`.`assignment_status_id`
                    WHERE `subjects_subject_id`='" . $subject . "'";

                            if ($search_text != "") {
                                $query .= " AND `assignment_code` LIKE '%" . $search_text . "%' OR `assignment` LIKE '%" . $search_text . "%'";
                            }

                            if ($sort != 0) {
                                if ($sort == 3) {
                                    $query .= " AND `assignment_status_assignment_status_id` = 1";
                                } else if ($sort == 4) {
                                    $query .= " AND `assignment_status_assignment_status_id` = 2";
                                } else if ($sort == 5) {
                                    $query .= " AND `assignment_status_assignment_status_id` = 3";
                                } else if ($sort == 6) {
                                    $query .= " AND `assignment_status_assignment_status_id` = 4";
                                } else if ($sort == 7) {
                                    $query .= " AND `assignment_status_assignment_status_id` = 5";
                                } else if ($sort == 8) {
                                    $query .= " AND `assignment_status_assignment_status_id` = 6";
                                } else if ($sort == 9) {
                                    $query .= " AND `assignment_status_assignment_status_id` = 7";
                                }
                            }

                            if ($sortByDate != 0) {
                                $sort = $sortByDate == 1 ? 'ASC' : 'DESC';
                                if (strpos($query, 'ORDER BY') === false) {
                                    $query .= " ORDER BY `added_date` " . $sort;
                                } else {
                                    $query .= ", `added_date` " . $sort;
                                }
                            }

                            if ($sortByDate == 0) {
                                $query .= " ORDER BY `added_date` DESC";
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
                            $assignments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                            <table class="table bl-solid m-0 p-0 g-0" id="assignments-table">
                                <thead>
                                    <tr class="br-t-5">
                                        <th scope="col-3" class="px-4 py-3 bg-table-header main-text fw-medium">
                                            <div class="d-flex flex-row align-items-center">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                <span class="text-14 main-text fw-medium mx-2">Assignment Code</span>
                                            </div>
                                        </th>
                                        <th scope="col-3" class="py-3 bg-table-header text-14 main-text fw-medium">Topic</th>
                                        <th scope="col-3" class="py-3 bg-table-header text-14 main-text fw-medium">Start Date</th>
                                        <th scope="col-3" class="py-3 bg-table-header text-14 main-text fw-medium">End Date</th>
                                        <th scope="col-3" class="py-3 bg-table-header text-14 main-text fw-medium">Duration</th>
                                        <th scope="col-3" class="py-3 bg-table-header text-14 main-text fw-medium">Document</th>
                                        <th scope="col-3" class="py-3 bg-table-header text-14 main-text fw-medium">
                                            <div class="d-flex justify-content-between align-items-center cursor-pointer" onclick="handleSubjectHasAssignmentsPagination(<?= $subject ?>,'sortByDate')">
                                                <span class="text-14 main-text fw-medium me-2">Date Added</span>
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
                                        <th scope="col-3" class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                        <?php
                                        $allowedPermissions = [16, 17, 18, 19, 20];
                                        $haveAnyPermission = false;

                                        foreach ($allowedPermissions as $permission) {
                                            if (UserPermissions::hasPermission('assignments', $permission, $subject)) {
                                                $haveAnyPermission = true;
                                            }
                                        }

                                        if (SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission || $validLecturer) {
                                        ?>
                                            <th scope="col-3" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                        <?php
                                        }

                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 10, $subject) || UserPermissions::hasPermission('assignments', 11, $subject)) {
                                        ?>
                                            <th scope="col-3" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <?php
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php

                                    if ($assignments->num_rows > 0) {
                                        while ($data = $assignments->fetch_assoc()) {
                                    ?>
                                            <tr>
                                                <td scope="row" class="px-4 py-4 align-middle">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                        <span class="text-14 main-text mx-2"><?php echo $data["assignment_code"] ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 align-middle"><span class="text-14 main-text"><?php echo $data["assignment"] ?></span></td>
                                                <?php

                                                $startDateTime = new DateTime($data["start_date"]);
                                                $endDateTime = new DateTime($data["end_date"]);

                                                $interval = $startDateTime->diff($endDateTime);

                                                $days = $interval->days;
                                                $weeks = floor($interval->days / 7);

                                                ?>
                                                <td class="py-4 align-middle"><span class="text-14 main-text"><?= !empty($data["start_date"]) ? date("Y-m-d h:i A", strtotime($data["start_date"])) : " N/A " ?>
                                                    </span></td>
                                                <td class="py-4 align-middle"><span class="text-14 main-text"><?= $data["end_date"] != '' ? date("Y-m-d h:i A", strtotime($data["end_date"])) : " N/A " ?></span></td>
                                                <td class="py-4 align-middle"><span class="text-14 main-text"><?= is_int($weeks) ? $weeks . " Weeks" : ($days == 0 ? " - - " : $days . " Days") ?></span></td>
                                                <td class="py-4 align-middle">
                                                    <?php
                                                    if (!empty($data["attachment"]) && file_exists("../assets/uploads/assignments-attachments/" . $data["attachment"])) {
                                                    ?>
                                                        <button class="table-btn table-btn-pink active" onclick="viewAssignmentsAttachement('<?= $data['attachment']; ?>');">View</button>
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <span class="text-14 main-text">No file attached</span>
                                                    <?php
                                                    }
                                                    ?>
                                                </td>
                                                <td class="py-4 align-middle"><span class="text-14 main-text"><?= $data["added_date"] != '' ? date("Y-m-d h:i A", strtotime($data["added_date"])) : " N/A " ?></span></td>
                                                <td class="py-2 align-middle">
                                                    <?php
                                                    $status_classes = [
                                                        "1"  => "table-btn-yellow",
                                                        "2"  => "table-btn-primary",
                                                        "3"  => "table-btn-danger",
                                                        "4"  => "table-btn-orange",
                                                        "5"  => "table-btn-purple",
                                                        "6"  => "table-btn-success",
                                                        "7"  => "table-btn-pink"

                                                    ];
                                                    $status = "Not Scheduled";
                                                    $statusId = '1';
                                                    if ($data["assignment_status_assignment_status_id"] == 2) {
                                                        $timezone = new DateTimeZone("Asia/Colombo");
                                                        $today = new DateTime("now", $timezone);
                                                        $todayDate = $today->format('Y-m-d H:i:s'); // Changed to 24-hour format

                                                        $status = "Scheduled";
                                                        $statusId = '2';

                                                        // Create DateTime objects for comparison
                                                        $startDateObj = new DateTime($data["start_date"], $timezone);
                                                        $endDateObj = new DateTime($data["end_date"], $timezone);

                                                        // Compare DateTime objects directly (more reliable)
                                                        if ($today >= $startDateObj && $today <= $endDateObj) {
                                                            $status = "Ongoing";
                                                            $statusId = '6';
                                                        } else if ($today > $endDateObj) {
                                                            $status = "Pending Marking";
                                                            $statusId = '7';
                                                        }
                                                    } else {
                                                        $status = $data["assignment_status"];
                                                        $statusId = $data["assignment_status_assignment_status_id"];
                                                    }
                                                    $sbutton_class = $status_classes[$statusId];
                                                    ?>
                                                    <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $status; ?></button>
                                                </td>
                                                <?php
                                                $allowedPermissions = [16, 17, 18, 19, 20];
                                                $haveAnyPermission = false;

                                                foreach ($allowedPermissions as $permission) {
                                                    if (UserPermissions::hasPermission('assignments', $permission, $subject)) {
                                                        $haveAnyPermission = true;
                                                    }
                                                }

                                                if (SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission || $validLecturer) {
                                                ?>
                                                    <td class="py-2 align-middle">
                                                        <div class="d-flex flex-row align-items-center p-0 g-0 m-0 gap-3">
                                                            <button class="table-btn table-btn-dark-theme" onclick="window.location.href='assignment-students-performance.php?assignment-id=<?= $data['assignment_id']; ?>' ">View</button>
                                                        </div>
                                                    </td>
                                                <?php

                                                }

                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 10, $subject) || UserPermissions::hasPermission('assignments', 11, $subject)) {
                                                ?>

                                                    <td class="py-3 align-middle">
                                                        <div class="dropdown br-0">
                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                            <ul class="dropdown-menu scrolable border-0 shadow w-100 m-2 br-5" aria-labelledby="dropdownMenuButton">
                                                                <?php
                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 10, $subject)) {
                                                                ?>
                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-assignment.php?assignment-id=<?= $data['assignment_id']; ?>">
                                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                            Edit</a></li>
                                                                    <hr class="mt-1 mb-0 me-2 mx-2">
                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick="cancelAssignment(<?= $data['assignment_id'] ?>,<?= $subject ?>)">
                                                                            <iconify-icon icon="lets-icons:cancel" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                            Cancel</a></li>
                                                                <?php
                                                                }
                                                                ?>
                                                                <?php
                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('assignments', 11, $subject)) {

                                                                    $studentsSubmit =  Database::search("SELECT COUNT(`students_has_assignments_id`) AS 'count' FROM `students_has_assignments` WHERE `assignments_assignment_id`='" . $data['assignment_id'] . "'")->fetch_assoc()['count'];
                                                                    if ($studentsSubmit == 0) {
                                                                ?>
                                                                        <hr class="mt-1 mb-0 me-2 mx-2">
                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick="deleteAssignment(<?= $data['assignment_id'] ?>,<?= $subject ?>)">
                                                                                <iconify-icon icon="material-symbols:delete-outline" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                Remove</a></li>
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleSubjectHasAssignmentsPagination(<?= $subject ?>,'previousPage')">
                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                        </button>
                                        <!-- Page Numbers (col-10, centered) -->
                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                            <ul class="pagination-list d-flex mb-0">
                                                <?php
                                                $shudderPages1 = false;
                                                $shudderPages2 = false;

                                                function renderPageButton($subject, $i, $isActive = false)
                                                {
                                                    $activeClass = $isActive ? 'active' : '';
                                                    $action = $isActive ? '' : "onclick=\"handleSubjectHasAssignmentsPagination('{$subject}','goToPage', '{$i}')\"";
                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                }

                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                        renderPageButton($subject, $i, $pageno == $i);
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleSubjectHasAssignmentsPagination(<?= $subject ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                            <span class="text-14 main-text fw-semibold">Next</span>
                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                        </button>
                                    </div>
                                </nav>
                            </div>
<?php
                        }
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


?>