<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                 
                    $subject = isset($decoded['subjectId']) ? $decoded['subjectId'] : '';
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
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
                        $allowedPermissions = [1, 2, 4, 10, 11];
                        $haveAnyPermission = false;
                        foreach ($allowedPermissions as $permission) {
                            if (UserPermissions::hasPermission('quiz', $permission, $subject)) {
                                $haveAnyPermission = true;
                            }
                        }
                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || $haveAnyPermission || $validLecturer)) {
                            $status->error = 'You are not authorized to filter lectures.';
                        } else {

                            $query = "SELECT `quiz`.`quiz_id`,`exams`.`exam_id`,`exams`.`exam_name`,`exams`.`exam_date`,`quiz`.`start_time`,`quiz`.`end_date`,`quiz`.`end_time`,`quiz`.`duration`,`quiz`.`users_user_id`,`users`.`user_fname`,`user_lname`,`users`.`profile_img`,`title`.`title` FROM `quiz`
                                                                                        INNER JOIN `exams` ON `quiz`.`exams_exam_id`=`exams`.`exam_id`
                                                                                        INNER JOIN  `users` ON `quiz`.`users_user_id`=`users`.`user_id` 
                                                                                        INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id`=`user_roles`.`user_role_id`
                                                                                        INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                                                                                        WHERE `subjects_subject_id`='" . $subject . "'";

                            if ($search_text != "") {
                                $query .= " AND `exam_name` LIKE '%" . $search_text . "%'";
                            }

                            if ($sort != 0) {
                                date_default_timezone_set('Asia/Colombo');
                                $current_datetime = date('Y-m-d H:i:s');

                                if ($sort == 1) {
                                    $query .= " AND CONCAT(`exams`.`exam_date`, ' ', `quiz`.`start_time`) > '$current_datetime'";
                                } else if ($sort == 2) {
                                    $query .= " AND CONCAT(`exams`.`exam_date`, ' ', `quiz`.`start_time`) <= '$current_datetime' 
                                        AND CONCAT(`exams`.`exam_date`, ' ', `quiz`.`end_time`) >= '$current_datetime'";
                                } else if ($sort == 3) {
                                    $query .= " AND CONCAT(`exams`.`exam_date`, ' ', `quiz`.`end_time`) < '$current_datetime'";
                                }
                            }
                            $query .= " ORDER BY `quiz`.`date_added` DESC";

                            $results = Database::search($query);
                            $results_count = $results->num_rows;
                            $result_per_page = (int)$resultPerPage;

                            if ($results_count > $result_per_page) {
                                $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                            } else {
                                $number_of_pages = 1;
                            }

                            $page_results = ($pageno - 1) * $result_per_page;
                            $quizes = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                            <table class="table bl-solid m-0 p-0 g-0" id="quizes-table">
                                <thead>
                                    <tr class="br-t-5">
                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                            <div class="d-flex flex-row align-items-center">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all">
                                                <span class="text-14 main-text fw-medium mx-2">Quiz Title</span>
                                            </div>
                                        </th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Start Date Time</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">End Date Time</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Add User</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header">
                                            <div class="d-flex flex-row align-items-center">
                                                <span class="text-14 main-text fw-medium">Status</span>
                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            </div>
                                        </th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>

                                        <?php
                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('quiz', 10, $subject) || UserPermissions::hasPermission('quiz', 11, $subject)) {
                                        ?>
                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <?php
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody id="user-table-data">
                                    <?php

                                    if ($quizes->num_rows > 0) {
                                        while ($data = $quizes->fetch_assoc()) {
                                    ?>
                                            <tr>
                                                <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                            <iconify-icon icon="material-symbols:quiz-outline" width="20" height="20" class="dark-theme"></iconify-icon>
                                                        </div>
                                                        <span class="text-14 main-text mx-2"><?php echo $data["exam_name"]; ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["exam_date"] . " " . CheckDateTime::formatTimeTo12Hour($data["start_time"]); ?></span></td>
                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["end_date"] . " " . CheckDateTime::formatTimeTo12Hour($data["end_time"]); ?></span></td>

                                                <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                        <?php
                                                        if (!empty($data["profile_img"]) && file_exists('../assets/imgs/users/' . $data["profile_img"])) {
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
                                                <td class="px-4 py-2 align-middle">
                                                    <?php

                                                    $status_classes = [
                                                        "Upcoming"     => "table-btn-primary",
                                                        "Ongoing"  => "table-btn-yellow",
                                                        "Completed"  => "table-btn-success",
                                                    ];
                                                    $sbutton_class = $status_classes[CheckDateTime::checkTimeStatus($data['exam_date'], $data['start_time'], $data['end_date'], $data['end_time'])];
                                                    ?>
                                                    <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo CheckDateTime::checkTimeStatus($data['exam_date'], $data['start_time'], $data['end_date'], $data['end_time']); ?></button>
                                                </td>

                                                <td class="px-4 py-3 align-middle">
                                                    <button class="table-btn table-btn-dark-theme active w-100" onclick="window.location.href='quiz-details.php?quiz-id=<?php echo $data['quiz_id']; ?>'">View</button>
                                                </td>
                                                <?php
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('quiz', 10, $subject) || UserPermissions::hasPermission('quiz', 11, $subject)) {
                                                ?>
                                                    <td class="px-4 py-3 align-middle">
                                                        <div class="dropdown">
                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                <?php
                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('quiz', 10, $subject)) {
                                                                ?>
                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="quiz-details?quiz-id=<?php echo $data['quiz_id']; ?>">
                                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                            Edit</a></li>
                                                                    <?php
                                                                }

                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('quiz', 11, $subject)) {
                                                                    if (CheckDateTime::checkTimeStatus($data['exam_date'], $data['start_time'], $data['end_date'], $data['end_time']) == "Upcoming") {
                                                                    ?>
                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick="removeQuiz(<?= $data['quiz_id'] ?>)">
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleQuizesPagination(<?= $subject ?>,'previousPage')">
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
                                                    $action = $isActive ? '' : "onclick=\"handleQuizesPagination('{$subject}','goToPage', '{$i}')\"";
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleQuizesPagination(<?= $subject ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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