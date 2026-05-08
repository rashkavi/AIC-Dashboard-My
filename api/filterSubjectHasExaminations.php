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
                    $type = isset($decoded['type']) ? $decoded['type'] : 0;
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
                        $allowedPermissions = [1, 2, 4, 10, 11, 17, 20];
                        $haveAnyPermission = false;
                        foreach ($allowedPermissions as $permission) {
                            if (UserPermissions::hasPermission('assignments', $permission, $subject)) {
                                $haveAnyPermission = true;
                            }
                        }
                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission || $validLecturer)) {
                            $status->error = 'You are not authorized to filter lectures.';
                        } else {

                            $query = "SELECT * FROM `exams` INNER JOIN `exam_types` 
                    ON `exams`.`exam_types_exam_type_id`=`exam_types`.`exam_type_id` WHERE 
                    `subjects_subject_id`='" . $subject . "' AND `exam_types_exam_type_id` != '3'";

                            if ($search_text != "") {
                                $query .= " AND `exam_name` LIKE '%" . $search_text . "%' OR `exam_type` LIKE '%" . $search_text . "%'";
                            }

                            if ($type != 0) {
                                $query .= " AND `exam_types_exam_type_id`='" . $type . "'";
                            }

                            $query .= " ORDER BY `date_added` DESC";

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
                            <table class="table bl-solid m-0 p-0 g-0" id="examinations-table">
                                <thead>
                                    <tr class="br-t-5">
                                        <th scope="col-3" class="px-4 py-3 bg-table-header main-text fw-medium">
                                            <div class="d-flex flex-row align-items-center">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                <span class="text-14 main-text fw-medium">Examination</span>
                                            </div>
                                        </th>
                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Exam Type</th>
                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Examination Date</th>
                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Schedule Date</th>
                                        <th scope="col-2" class="px-4 py-3 bg-table-header">
                                            <div class="d-flex flex-row align-items-center">
                                                <span class="text-14 main-text fw-medium">Status</span>
                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            </div>
                                        </th>
                                        <th scope="col-3" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                        <?php
                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('exams', 10, $subject)) {
                                        ?>
                                            <th scope="col-2" class="px-4 py-3 bg-table-header"></th>
                                        <?php
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php

                                    if ($exams->num_rows > 0) {
                                        while ($data = $exams->fetch_assoc()) {
                                    ?>
                                            <tr>
                                                <td scope="row" class="px-4 pt-3 pb-3">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                        <span class="text-14 main-text mx-2"><?= $data["exam_name"] ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["exam_type"] ?></span></td>
                                                <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["exam_date"] ?></span></td>
                                                <td class="px-4 pt-3 pb-3 align-middle"><span class="text-14 main-text"><?= $data["date_added"] ?></span></td>

                                                <td class="px-4 pt-3 pb-3 align-middle">
                                                    <?php

                                                    $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));
                                                    $examDate = new DateTime($data["exam_date"]);
                                                    $startTime;
                                                    $endTime;
                                                    $isScheduledExams = false;
                                                    if ($data["exam_type_id"] == 1) {
                                                        $physical_exam = Database::search("SELECT `start_time`,`end_time` FROM `physical_exam` WHERE `exams_exam_id`='" . $data["exam_id"] . "'");
                                                        if ($physical_exam->num_rows > 0) {
                                                            $physical_exam = $physical_exam->fetch_assoc();
                                                            $startTime = $physical_exam["start_time"];
                                                            $endTime = $physical_exam["end_time"];
                                                            $isScheduledExams = true;
                                                        }
                                                    } else if ($data["exam_type_id"] == 2) {
                                                        $online_exam = Database::search("SELECT `start_time`,`end_time` FROM `online_exam` WHERE `exams_exam_id`='" . $data["exam_id"] . "'");
                                                        if ($online_exam->num_rows > 0) {
                                                            $online_exam = $online_exam->fetch_assoc();
                                                            $startTime = $online_exam["start_time"];
                                                            $endTime = $online_exam["end_time"];
                                                            $isScheduledExams = true;
                                                        }
                                                    }
                                                    if (!$isScheduledExams) {
                                                    ?>
                                                        <button class="table-btn table-btn-danger w-100">Not-Scheduled</button>
                                                        <?php
                                                    } else {
                                                        if ($dateTime->format("Y-m-d") === $examDate->format("Y-m-d") && $dateTime->format("H:i:s") >= $startTime && $dateTime->format("H:i:s") <= $endTime) {
                                                        ?>
                                                            <button class="table-btn table-btn-yellow w-100">Ongoing</button>
                                                        <?php
                                                        } else if ($dateTime->format("Y-m-d") > $examDate->format("Y-m-d") || ($dateTime->format("Y-m-d") === $examDate->format("Y-m-d") && $dateTime->format("H:i:s") > $endTime)) {
                                                        ?>
                                                            <button class="table-btn table-btn-success w-100">Completed</button>
                                                        <?php
                                                        } else if ($dateTime->format("Y-m-d") < $examDate->format("Y-m-d") || ($dateTime->format("Y-m-d") === $examDate->format("Y-m-d") && $dateTime->format("H:i:s") < $startTime)) {
                                                        ?>
                                                            <button class="table-btn table-btn-purple w-100">Upcoming</button>
                                                    <?php
                                                        }
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-4 pt-2 pb-2 align-middle">
                                                    <?php
                                                    if ($isScheduledExams) {
                                                    ?>
                                                        <button class="table-btn table-btn-dark-theme" onclick="window.location.href='subject-examination?examination-id=<?= $data['exam_id']; ?>' ">View</button>
                                                    <?php
                                                    }
                                                    ?>

                                                </td>
                                                <?php
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('exams', 10, $subject)) {
                                                ?>

                                                    <td class="px-4 py-3 align-middle">
                                                        <div class="dropdown">
                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="update-examination?examination-id=<?= $data['exam_id']; ?>">
                                                                        <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                        Edit</a></li>
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleSubjectHasExaminationsPagination(<?= $subject ?>,'previousPage');">
                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                        </button>
                                        <!-- Page Numbers (col-10, centered) -->
                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                            <ul class="pagination-list d-flex mb-0">
                                                <?php
                                                $shudderPages1 = false;
                                                $shudderPages2 = false;

                                                function renderPageButton($i, $isActive = false, $subject)
                                                {
                                                    $activeClass = $isActive ? 'active' : '';
                                                    $action = $isActive ? '' : "onclick=\"handleSubjectHasExaminationsPagination('{$subject}','goToPage', '{$i}')\"";
                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                }

                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                        renderPageButton($i, $pageno == $i, $subject);
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleSubjectHasExaminationsPagination(<?= $subject ?>,'nextPage',<?= $pageno ?>,<?= $number_of_pages ?>);">
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