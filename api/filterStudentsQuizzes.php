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

                    $id = isset($decoded['quizId']) ? $decoded['quizId'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $filter = isset($decoded['filter']) ? $decoded['filter'] : 0;
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $quiz = Database::search("SELECT * FROM `quiz` INNER JOIN `exams` ON `quiz`.`exams_exam_id` = `exams`.`exam_id` WHERE `quiz_id` = '" . $id . "'");
                    if ($quiz->num_rows == 0) {
                        $status->error = "Invalid quiz Id";
                    } else {
                        $quizData = $quiz->fetch_assoc();
                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                        $validLecturer = false;

                        $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $quizData['subjects_subject_id'] . "'");
                        if ($lecturerSubject->num_rows > 0) {
                            $validLecturer = true;
                        }

                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('quiz', 1, $quizData['subjects_subject_id']) || UserPermissions::hasPermission('quiz', 17, $quizData['subjects_subject_id']) || $validLecturer)) {
                            $status->error = 'You are not authorized to filter students quizzes.';
                        } else {

                            $query = "SELECT * FROM `students_has_quiz` 
                                    INNER JOIN `students` ON `students_has_quiz`.`students_student_id`=`students`.`student_id`
                                    INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                                    INNER JOIN `quiz` ON `students_has_quiz`.`quiz_quiz_id`=`quiz`.`quiz_id`
                                    INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                                    INNER JOIN `exam_status` ON `students_has_quiz`.`exam_status_exam_status_id`=`exam_status`.`exam_status_id`
                                    WHERE `quiz`.`quiz_id`='" . $id . "' ";

                            if ($search_text != "") {
                                $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%')";
                            }

                            if ($filter != 0) {
                                $query .= " AND `exam_status`.`exam_status_id` = '" . $filter . "'";
                            }

                            if ($sort != 0) {
                                if ($sort == 1) {
                                    $query .= " ORDER BY `marks` DESC";
                                } else if ($sort == 2) {
                                    $query .= " ORDER BY `marks` ASC";
                                } else if ($sort == 3) {
                                    $query .= " ORDER BY `submitted_at` DESC";
                                } else if ($sort == 4) {
                                    $query .= " ORDER BY `submitted_at` ASC";
                                }
                            } else {
                                $query .= " ORDER BY `submitted_at` ASC";
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
                            $quizzes = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                            <table class="table bl-solid m-0 p-0 g-0" id="students-quiz-table">
                                <thead>
                                    <tr class="br-t-5">
                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                            <div class="d-flex flex-row align-items-center">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                <span class="text-14 main-text fw-medium mx-2">Student</span>
                                            </div>
                                        </th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Submitted Date</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>

                                    </tr>
                                </thead>
                                <tbody id="user-table-data">
                                    <?php

                                    if ($quizzes->num_rows > 0) {
                                        while ($data = $quizzes->fetch_assoc()) {
                                    ?>
                                            <tr>
                                                <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["student_id"]; ?>" onchange="checkRowEvents(<?= $data['student_id']; ?>);">
                                                        <?php
                                                        if (!empty($data["profile_img"]) && file_exists('../assets/imgs/students/' . $data["profile_img"])) {
                                                        ?>
                                                            <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Student image">
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
                                                </td>
                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["submitted_at"]; ?></span></td>
                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["marks"]; ?>%</span></td>

                                                <td class="px-4 py-3 align-middle">
                                                    <button class="table-btn table-btn-dark-theme active w-100" onclick="window.location.href='student-quiz-performance.php?student-id=<?= $data['student_id']; ?>'">View</button>
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsQuizPagination(<?= $id ?>,'previousPage')">
                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                        </button>
                                        <!-- Page Numbers (col-10, centered) -->
                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                            <ul class="pagination-list d-flex mb-0">
                                                <?php
                                                $shudderPages1 = false;
                                                $shudderPages2 = false;

                                                function renderPageButton($id, $i, $isActive = false)
                                                {
                                                    $activeClass = $isActive ? 'active' : '';
                                                    $action = $isActive ? '' : "onclick=\"handleStudentsQuizPagination('{$id}','goToPage', '{$i}')\"";
                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                }

                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                        renderPageButton($id, $i, $pageno == $i);
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsQuizPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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
