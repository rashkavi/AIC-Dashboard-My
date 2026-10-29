<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                
                    $id = isset($decoded['assignmentId']) ? $decoded['assignmentId'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $filter = isset($decoded['filter']) ? $decoded['filter'] : 0;
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $assignment = Database::search("SELECT * FROM `assignments` WHERE `assignment_id` = '" . $id . "'");
                    $assignmentData = $assignment->fetch_assoc();

                    $isLoginUserSubjectLecturer = Database::search("SELECT `lecturers_id`,`user_fname`,`user_lname`,`profile_img`,`title`,`per_assignment_fee` FROM `lecturers`
                    INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                    WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $assignmentData['subjects_subject_id'] . "'");
                    if ($isLoginUserSubjectLecturer->num_rows == 0) {
                        $status->error = "You are not a lecturer of this subject";
                    } else {
                        $lecturerData = $isLoginUserSubjectLecturer->fetch_assoc();

                        $query = "SELECT DISTINCT(`students_has_assignments_id`),`students_has_assignments_id`,`title`,`first_name`,`last_name`,`profile_img`,`students_has_intakes`.`student_id`,`uploaded_at`,`upload`,`submission_id`,`submission`,`marks`,`assignment_marks_status`,`assignment_marks_status_id` FROM `students_has_assignments` 
                                                                    INNER JOIN `students` ON `students_has_assignments`.`students_student_id`=`students`.`student_id`
                                                                    INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                                                                    INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                                                                    INNER JOIN `assignments` ON `students_has_assignments`.`assignments_assignment_id` = `assignments`.`assignment_id`
                                                                    INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                    INNER JOIN `students_has_intakes` ON (`students_has_assignments`.`students_student_id` = `students_has_intakes`.`students_student_id`
                                                                    AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`)
                                                                    INNER JOIN `assignment_submission` ON `students_has_assignments`.`submission_submission_id`=`assignment_submission`.`submission_id`
                                                                    INNER JOIN `lecturers_has_students_has_assignments` ON `lecturers_has_students_has_assignments`.`students_has_assignments_students_has_assignments_id`=`students_has_assignments`.`students_has_assignments_id`
                                                                    INNER JOIN `assignment_marks_status` ON `students_has_assignments`.`assignment_marks_status_assignment_marks_status_id` = `assignment_marks_status`.`assignment_marks_status_id`
                                                                    WHERE `students_has_assignments`.`assignments_assignment_id`='" . $id . "'
                                                                    AND `lecturers_has_students_has_assignments`.`lecturers_lecturers_id`='" . $lecturerData['lecturers_id'] . "'
                                                                    ";
                        if ($search_text != "") {
                            $query .= " AND `first_name` LIKE '%" . $search_text . "%' OR `students_has_intakes`.`student_id` LIKE '%" . $search_text . "%'";
                        }

                        if ($filter != 0) {
                            $query .= " AND `students_has_assignments`.`submission_submission_id` = '" . $filter . "'";
                        }

                        if ($sort != 0) {
                            if ($sort == 1) {
                                $query .= " ORDER BY `uploaded_at` DESC";
                            } else if ($sort == 2) {
                                $query .= " ORDER BY `uploaded_at` ASC";
                           } else if ($sort == 3) {
                                $query .= " AND `students_has_assignments_id` IN (SELECT `students_has_assignments_students_has_assignments_id` FROM `lecturers_has_students_has_assignments` WHERE `submission_sts_submission_sts_id`='2')";
                            } else if ($sort == 4) {
                                $query .= " AND `students_has_assignments_id` IN (SELECT `students_has_assignments_students_has_assignments_id` FROM `lecturers_has_students_has_assignments` WHERE `submission_sts_submission_sts_id`='1')";
                            } else if ($sort == 5) {
                                $query .= " AND `assignment_marks_status_assignment_marks_status_id`='3'";
                            } else if ($sort == 6) {
                                $query .= " AND (`assignment_marks_status_assignment_marks_status_id` IN (4,5))";
                            }
                        } else {
                            $query .= " ORDER BY `uploaded_at` DESC";
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
                        <table class="table bl-solid m-0 p-0 g-0" id="students-assignments-table">
                            <thead>
                                <tr class="br-t-5">
                                    <th scope="col-3" class="px-4 py-3 bg-table-header main-text fw-medium">
                                        <div class="d-flex flex-row align-items-center">
                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                            <span class="text-14 main-text fw-medium">Student</span>
                                        </div>
                                    </th>
                                    <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>
                                    <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Upload Date & Time</th>
                                    <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Assignment</th>
                                    <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks</th>
                                    <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks Status</th>
                                    <th scope="col-2" class="px-4 py-3 bg-table-header">
                                        <div class="d-flex flex-row align-items-center">
                                            <span class="text-14 main-text fw-medium">Submission Status</span>
                                            <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                        </div>
                                    </th>
                                    <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                               
                                if ($assignments->num_rows > 0) {
                                    while ($data = $assignments->fetch_assoc()) {

                                ?>
                                        <tr class="br-t-5">
                                            <td scope="row" class="px-4 pt-3 pb-3">
                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">

                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" disabled>

                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
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
                                            <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["student_id"]; ?></span></td>
                                            <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["uploaded_at"]; ?></span></td>

                                            <td class="px-4 pt-3 pb-3 align-middle">
                                                <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                    <button class="table-btn table-btn-dark-theme active" onclick="downloadSelectedStudentAssignment('<?= $data['upload']; ?>')">View</button>
                                                </div>
                                            </td>
                                            <td class="px-4 pt-3 pb-3 align-middle">
                                                <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                    <?php
                                                    if ($data['marks'] == null) {
                                                    ?>
                                                        <button class="table-btn table-btn-purple">Pending</button>
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <span class="text-14 main-text"><?= $data['marks']; ?></span>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-2 align-middle">
                                                <?php
                                                $status_classes = [
                                                    "1"  => "table-btn-yellow",
                                                    "2"  => "table-btn-pink",
                                                    "3"  => "table-btn-orange",
                                                    "4"  => "table-btn-success"
                                                ];
                                                if ($data["assignment_marks_status_id"] == 5) {
                                                    $sbutton_class = $status_classes[4];
                                                ?>
                                                    <button class="table-btn <?php echo $sbutton_class; ?> w-100"> Mark Released</button>
                                                <?php
                                                } else {
                                                    $sbutton_class = $status_classes[$data["assignment_marks_status_id"]];
                                                ?>
                                                    <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["assignment_marks_status"]; ?></button>
                                                <?php
                                                }

                                                ?>

                                            </td>
                                            <td class="px-4 py-2 align-middle">
                                                <?php
                                                $status_classes = [
                                                    "1"  => "table-btn-success",
                                                    "2"  => "table-btn-yellow",
                                                    "3"  => "table-btn-danger",
                                                ];
                                                $sbutton_class = $status_classes[$data["submission_id"]];
                                                ?>
                                                <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["submission"]; ?> Submission</button>
                                            </td>
                                            <td class="px-4 py-2 align-middle">
                                                <?php
                                                $studentName = $data["first_name"] . " " . $data["last_name"];

                                                if ($data["marks"] == "") {
                                                ?>
                                                    <button class="table-btn table-btn-purple active" onclick="loadLecturerAddMarkModel('<?= $id ?>','<?= $assignmentData['assignment_code'] ?>','<?= $assignmentData['assignment'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['students_has_assignments_id']; ?>')">Add Mark</button>
                                                <?php
                                                } else {
                                                ?>
                                                    <button class="table-btn table-btn-danger active" onclick="loadLecturerEditMarkModel('<?= $id ?>','<?= $assignmentData['assignment_code'] ?>','<?= $assignmentData['assignment'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['marks'] ?>','<?= $data['students_has_assignments_id']; ?>')">Edit Mark</button>
                                                <?php
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
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLecturersPagination(<?= $id ?>,'previousPage')">
                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                    </button>
                                    <!-- Page Numbers (col-10, centered) -->
                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                        <ul class="pagination-list d-flex mb-0">
                                            <?php
                                            $shudderPages1 = false;
                                            $shudderPages2 = false;

                                            function renderPageButton2($id, $i, $isActive = false)
                                            {
                                                $activeClass = $isActive ? 'active' : '';
                                                $action = $isActive ? '' : "onclick=\"handleLecturersPagination('{$id}','goToPage', '{$i}')\"";
                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                            }

                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                    renderPageButton2($id, $i, $pageno == $i);
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
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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


?>