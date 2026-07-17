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

                    $subject = isset($decoded['subjectId']) ? $decoded['subjectId'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $update = isset($decoded['update']) ? $decoded['update'] : 0;
                    $selectStudents = isset($decoded['selectStudents']) ? $decoded['selectStudents'] : [];
                    $unSelectStudents = isset($decoded['unSelectStudents']) ? $decoded['unSelectStudents'] : [];

                    if (empty($subject) || $subject == 0) {
                        $status->error = 'Invalid subject id';
                    } else {

                        $isValidSubject = Database::search("SELECT COUNT(*) as 'count' FROM `subjects` WHERE `subject_id` = '" . $subject . "'");
                        if ($isValidSubject->num_rows == 0) {
                            $status->error = 'Invalid subject id';
                        } else {

                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                            $validLecturer = false;
                            $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $subject . "'");
                            if ($lecturerSubject->num_rows > 0) {
                                $validLecturer = true;
                            }

                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('subject_students', $subject) || $validLecturer)) {
                                $status->error = 'You are not authorized to filter lectures.';
                            } else {

                                $query = "SELECT
                                `students_has_subjects`.`students_has_subjects_id` AS `shsId`, 
                                `students`.`student_id`, 
                                `login_details`.`first_name`, 
                                `login_details`.`last_name`, 
                                `login_details`.`email`, 
                                `students`.`mobile`, 
                                `students`.`profile_img`, 
                                `students_has_intakes`.`addmission_date`, 
                                `title`.`title`, 
                                `status`.`status`,
                                `status`.`status_id`
                            FROM `students_has_subjects` 
                            INNER JOIN `students` 
                                ON `students_has_subjects`.`students_student_id` = `students`.`student_id`
                            INNER JOIN `login_details`
                                ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                            INNER JOIN `subjects` ON `students_has_subjects`.`subjects_subject_id`=`subjects`.`subject_id`
                            INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
                            INNER JOIN `students_has_intakes` ON (`students`.`student_id`=`students_has_intakes`.`students_student_id` AND `levels`.`intakes_intake_id`=`students_has_intakes`.`intakes_intake_id`)
                           
                            INNER JOIN `intakes` 
                                ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                            INNER JOIN `title` 
                                ON `students`.`title_title_id` = `title`.`title_id`
                            INNER JOIN `status` 
                                ON `students_has_subjects`.`status_status_id` = `status`.`status_id`
                            WHERE `students_has_subjects`.`subjects_subject_id` = '" . $subject . "'
                        ";

                                if ($search_text != "") {
                                    $query .= " AND `first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%' OR `students_has_intakes`.`student_id` = '%" . $search_text . "%'";
                                }


                                if ($sort != 0) {
                                    if ($sort == 1) {
                                        $query .= " ORDER BY `first_name` ASC";
                                    } else if ($sort == 2) {
                                        $query .= " ORDER BY `first_name` DESC";
                                    } else if ($sort == 3) {
                                        $query .= " AND `status`.`status_id`='1'";
                                    } else if ($sort == 4) {
                                        $query .= " AND `status`.`status_id`='2'";
                                    } else if ($sort == 5) {
                                        $query .= " ORDER BY `students_has_subjects_id` DESC";
                                    } else if ($sort == 6) {
                                        $query .= " ORDER BY `students_has_subjects_id` ASC";
                                    }
                                }

                                if ($sort == 0) {
                                    $query .= "ORDER BY `students_has_subjects_id` DESC";
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
                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Email Address</th>
                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Mobile</th>
                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Admission Date</th>
                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                            <th scope="col" class="px-4 py-3 bg-table-header">
                                                <div class="d-flex flex-row align-items-center">
                                                    <span class="text-14 main-text fw-medium">Status</span>
                                                    <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                </div>
                                            </th>

                                            <?php

                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("subject_students", 4, $subject)) {
                                            ?>
                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
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
                                                            if ($isUpdateStudentStatus) {
                                                                if (count($selectStudents) > 0) {
                                                                    if (in_array($data['shsId'], $selectStudents)) {
                                                                        if ($data['status_id'] == 1 && $update == 2) {
                                                                            $isMatchSelectUpdate = true;
                                                                        } else if ($data['status_id'] == 2 && $update == 1) {
                                                                            $isMatchSelectUpdate = true;
                                                                        }
                                                                    }
                                                                }

                                                                if (count($unSelectStudents) > 0) {
                                                                    if (in_array($data['shsId'], $unSelectStudents)) {
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
                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" checked id="check-user<?php echo $data["shsId"]; ?>" onchange="checkRowEvents(<?php echo $data['shsId']; ?>);">
                                                            <?php
                                                            } else {
                                                            ?>
                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["shsId"]; ?>" onchange="checkRowEvents(<?php echo $data['shsId']; ?>);">
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
                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["student_id"]; ?></span></td>
                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>
                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["mobile"]; ?></span></td>
                                                    <td colspan="3" class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["addmission_date"]; ?></span></td>
                                                    <td class="px-4 py-2 align-middle">
                                                        <?php
                                                        $status_classes = [
                                                            "Active"     => "table-btn-success",
                                                            "Inactive"  => "table-btn-danger",
                                                        ];
                                                        $sbutton_class = $status_classes[$data["status"]];
                                                        ?>
                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["status"]; ?></button>
                                                    </td>

                                                    <?php

                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("subject_students", 4, $subject)) {
                                                    ?>
                                                        <td class="px-4 pt-3 pb-3 align-middle">
                                                            <button class="table-btn table-btn-dark-theme active w-100" onclick="window.location.href='student-details.php?student-id=<?php echo $data['student_id']; ?>'">View</button>
                                                        </td>
                                                        <td class="px-4 py-3 align-middle">
                                                            <div class="dropdown">
                                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">

                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-details?student-id=<?php echo $data['student_id']; ?>">
                                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                            Edit</a></li>
                                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                    <?php
                                                                    if ($data["status"] == "Active") {
                                                                    ?>
                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateSubjectStudentStatus(<?= $subject ?>,2,'<?php echo $data['shsId']; ?>')">
                                                                                <iconify-icon icon="lsicon:minus-filled" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                Deactivate</a></li>
                                                                    <?php
                                                                    } else {
                                                                    ?>
                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateSubjectStudentStatus(<?= $subject ?>,1,'<?php echo $data['shsId']; ?>')">
                                                                                <iconify-icon icon="dashicons:plus-alt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                Activate</a></li>
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
                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleSubjectAddStudentsPagination(<?= $subject ?>,'previousPage');">
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
                                                        $action = $isActive ? '' : "onclick=\"handleSubjectAddStudentsPagination('{$subject}','goToPage', '{$i}')\"";
                                                        echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                    }

                                                    for ($i = 1; $i <= $number_of_pages; $i++) {
                                                        if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                            renderPageButton($subject, $i, $pageno == $i);
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
                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleSubjectAddStudentsPagination(<?= $subject ?>,'nextPage',<?= $number_of_pages ?>);">
                                                <span class="text-14 main-text fw-semibold">Next</span>
                                                <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            </button>
                                        </div>
                                    </nav>
                                </div>
<?php
                            }
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
