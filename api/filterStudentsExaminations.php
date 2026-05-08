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
                  
                    $id = isset($decoded['examId']) ? $decoded['examId'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $filter = isset($decoded['filter']) ? $decoded['filter'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $examination = Database::search("SELECT * FROM `exams` WHERE `exam_id` = '" . $id . "'");
                    if ($examination->num_rows == 0) {
                        $status->error = "Invalid examination Id";
                    } else {
                        $examinationData = $examination->fetch_assoc();

                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                        $validLecturer = false;

                        $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $examinationData['subjects_subject_id'] . "'");
                        if ($lecturerSubject->num_rows > 0) {
                            $validLecturer = true;
                        }

                        $allowedPermissions = [1, 17, 20];
                        $haveAnyPermission = false;

                        foreach ($allowedPermissions as $permission) {
                            if (UserPermissions::hasPermission('exams', $permission, $examinationData['subjects_subject_id'])) {
                                $haveAnyPermission = true;
                            }
                        }

                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission || $validLecturer)) {
                            $status->error = 'You are not authorized to filter student exams.';
                        } else {

                            $examType = $examinationData["exam_types_exam_type_id"];

                            $datetime = new DateTime('now', new DateTimeZone('Asia/Colombo'));
                            $formatedDateTime = $datetime->format('Y-m-d');

                            if ($examType == 1) {
                                $query = "SELECT `students_has_physical_exam_id` AS `student_exam_id`,`exam_name`,`marks`,`admission`,`exam_status`,`exam_status_id`,`students_has_intakes`.`student_id`,`first_name`,`last_name`,`title`,`profile_img` FROM `students_has_physical_exam`
                            INNER JOIN `students` ON `students_has_physical_exam`.`students_student_id`=`students`.`student_id`
                            INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                            INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                            INNER JOIN `physical_exam` ON `students_has_physical_exam`.`physical_exam_physical_exam_id` = `physical_exam`.`physical_exam_id`
                            INNER JOIN `exams` ON `physical_exam`.`exams_exam_id`=`exams`.`exam_id`
                            INNER JOIN `subjects` ON `exams`.`subjects_subject_id` = `subjects`.`subject_id`
                            INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                            INNER JOIN `students_has_intakes` ON (`students_has_physical_exam`.`students_student_id` = `students_has_intakes`.`students_student_id`
                            AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`)
                            INNER JOIN `exam_status` ON `students_has_physical_exam`.`exam_status_exam_status_id` = `exam_status`.`exam_status_id`
                            WHERE `exams`.`exam_id`='" . $id . "'
                            ";
                            } elseif ($examType == 2) {
                                $query = "SELECT `students_has_online_exam_id` AS `student_exam_id`,`exam_name`,`marks`,`exam_status`,`exam_status_id`,`students_has_intakes`.`student_id`,`first_name`,`last_name`,`title`,`profile_img` FROM `students_has_online_exam`
                            INNER JOIN `students` ON `students_has_online_exam`.`students_student_id`=`students`.`student_id`
                            INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                            INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                            INNER JOIN `online_exam` ON `students_has_online_exam`.`online_exam_online_exam_id` = `online_exam`.`online_exam_id`
                            INNER JOIN `exams` ON `online_exam`.`exams_exam_id`=`exams`.`exam_id`
                            INNER JOIN `subjects` ON `exams`.`subjects_subject_id` = `subjects`.`subject_id`
                            INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                            INNER JOIN `students_has_intakes` ON (`students_has_online_exam`.`students_student_id` = `students_has_intakes`.`students_student_id`
                            AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`)
                            INNER JOIN `exam_status` ON `students_has_online_exam`.`exam_status_exam_status_id` = `exam_status`.`exam_status_id`
                            WHERE `exams`.`exam_id`='" . $id . "'
                            ";
                            }

                            if ($search_text != "") {
                                $query .= " AND `first_name` LIKE '%" . $search_text . "%' OR `students_has_intakes`.`student_id` LIKE '%" . $search_text . "%'";
                            }

                            if ($filter != 0) {
                                $query .= " AND `exam_status`.`exam_status_id` = '" . $filter . "'";
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
                            $exams = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                            <table class="table bl-solid m-0 p-0 g-0" id="students-assignments-table">
                                <thead>
                                    <tr class="br-t-5">
                                        <th class="px-4 py-3 bg-table-header main-text fw-medium">
                                            <div class="d-flex flex-row align-items-center">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                <span class="text-14 main-text fw-medium">Student</span>
                                            </div>
                                        </th>
                                        <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>

                                        <?php
                                        if ($examType == 2) {
                                        ?>
                                            <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Answers</th>
                                        <?php
                                        } elseif ($examType == 1) {
                                        ?>
                                            <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Admission Id</th>
                                        <?php
                                        }

                                        ?>
                                        <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks</th>
                                        <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks Status</th>

                                        <?php
                                        if ($examinationData['exam_date'] < $formatedDateTime) {
                                            if (SessionUser::ifUserIsPro($sessionUserRole) || $validLecturer) {
                                        ?>
                                                <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                        <?php
                                            }
                                        }
                                        ?>

                                    </tr>
                                </thead>
                                <tbody>
                                    <?php

                                    if ($exams->num_rows > 0) {
                                        while ($data = $exams->fetch_assoc()) {

                                    ?>
                                            <tr class="br-t-5">
                                                <td scope="row" class="px-4 pt-3 pb-3">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">

                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" disabled>

                                                        <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?= $data['marks']; ?>);">
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
                                                <?php
                                                if ($examType == 1) {
                                                ?>
                                                    <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["admission"]; ?></span></td>
                                                <?php
                                                } else {
                                                ?>
                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                        <button class="table-btn table-btn-dark-theme" onclick="location.href = 'online-examination-answers?student-exam-id=<?= $data['student_exam_id'] ?>'">View Answers</button>
                                                    </td>
                                                <?php
                                                }
                                                ?>
                                                <?php
                                                if ($data['marks'] == null) {
                                                ?>
                                                    <td class="px-4 pt-3 pb-3 align-middle">
                                                        <button class="table-btn table-btn-yellow">Pending</button>
                                                    </td>
                                                <?php
                                                } else {
                                                ?>
                                                    <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["marks"]; ?></span></td>
                                                <?php
                                                }
                                                ?>

                                                <td class="px-4 py-2 align-middle">
                                                    <?php
                                                    $status_classes = [
                                                        "1"  => "table-btn-yellow",
                                                        "2"  => "table-btn-pink",
                                                        "3"  => "table-btn-success"
                                                    ];

                                                    $sbutton_class = $status_classes[$data["exam_status_id"]];
                                                    ?>
                                                    <button class="table-btn <?php echo $sbutton_class; ?> w-75"> <?php echo $data["exam_status"]; ?></button>
                                                    <?php

                                                    ?>
                                                </td>
                                                <?php
                                                if ($examinationData['exam_date'] < $formatedDateTime) {
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || $validLecturer) {
                                                ?>
                                                        <td class="px-4 py-2 align-middle">
                                                            <?php
                                                            $studentName = $data["first_name"] . " " . $data["last_name"];
                                                            if ($examType == 1) {
                                                                if ($data["marks"] == "") {
                                                            ?>
                                                                    <button class="table-btn table-btn-purple" onclick="loadLecturerExamAddMarkModel('<?= $id ?>','<?= $data['exam_name'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['student_exam_id']; ?>')">Add Mark</button>
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <button class="table-btn table-btn-danger" onclick="loadLecturerExamEditMarkModel('<?= $id ?>','<?= $data['exam_name'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['marks'] ?>','<?= $data['student_exam_id']; ?>')">Edit Mark</button>
                                                                <?php
                                                                }
                                                            } else {
                                                                if ($data["marks"] == "") {
                                                                ?>
                                                                    <button class="table-btn table-btn-purple" onclick="loadLecturerExamAddMarkModel('<?= $id ?>','<?= $data['exam_name'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['student_exam_id']; ?>','2')">Add Mark</button>
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <button class="table-btn table-btn-danger" onclick="loadLecturerExamEditMarkModel('<?= $id ?>','<?= $data['exam_name'] ?>','<?= $studentName ?>','<?= $data['student_id'] ?>','<?= $data['marks'] ?>','<?= $data['student_exam_id']; ?>','2')">Edit Mark</button>
                                                            <?php
                                                                }
                                                            }
                                                            ?>
                                                        </td>
                                                <?php
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
