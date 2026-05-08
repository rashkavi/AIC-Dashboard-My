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
                    $selectStudents = isset($decoded['selectStudents']) ? $decoded['selectStudents'] : [];
                    $unSelectStudents = isset($decoded['unSelectStudents']) ? $decoded['unSelectStudents'] : [];

                    if (empty($subject) || $subject == 0) {
                        $status->error = 'Invalid Subejct Id';
                    } else {

                        $subject_rs = Database::search("SELECT `subject_id`,`intake_id`,`level`,`level_id`,`subject`,`subject_group_subject_group_id` FROM `subjects`
                        INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
                        INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id` 
                        WHERE `subject_id` = '" . $subject . "'");

                        if ($subject_rs->num_rows == 0) {
                            $status->error = 'Invalid Subejct Id';
                        } else {
                            $subjectData = $subject_rs->fetch_assoc();

                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('subject_students', 2, $subject))) {
                                $status->error = 'You are not authorized to filter lectures.';
                            } else {

                                $query = " SELECT
                                                                
                                                                    `students`.`student_id` AS sid, 
                                                                    `students_has_intakes`.`student_id`, 
                                                                    `login_details`.`first_name`, 
                                                                    `login_details`.`last_name`, 
                                                                    `login_details`.`email`, 
                                                                    `students`.`mobile`, 
                                                                    `students`.`profile_img`, 
                                                                    `students_has_intakes`.`addmission_date`, 
                                                                    `title`.`title`, 
                                                                    `status`.`status`,
                                                                                                                                       
                                                                    IFNULL((
                                                                        SELECT `ps`.`payment_status` 
                                                                        FROM `course_payments` AS `cp`
                                                                        INNER JOIN `payment_status` AS `ps`
                                                                            ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                        WHERE `cp`.`payment_plan_has_students_has_intakes_id` IN (
                                                                        SELECT `payment_plan_has_students_has_intakes_id` FROM `payment_plan_has_students_has_intakes`
                                                                        WHERE `students_has_intakes_students_has_intakes_id`=`students_has_intakes`.`students_has_intakes_id`
                                                                        )
                                                                        ORDER BY `cp`.`date_paid` DESC LIMIT 1
                                                                    ), 'No Payment') AS `last_payment_status`,
                                                                     IFNULL((
                                                                        SELECT `ps`.`payment_status_id` 
                                                                        FROM `course_payments` AS `cp`
                                                                        INNER JOIN `payment_status` AS `ps`
                                                                            ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                        WHERE `cp`.`payment_plan_has_students_has_intakes_id` IN (
                                                                        SELECT `payment_plan_has_students_has_intakes_id` FROM `payment_plan_has_students_has_intakes`
                                                                        WHERE `students_has_intakes_students_has_intakes_id`=`students_has_intakes`.`students_has_intakes_id`
                                                                        )
                                                                        ORDER BY `cp`.`date_paid` DESC LIMIT 1
                                                                    ), 0) AS `last_payment_status_id`

                                                                FROM `students_has_intakes` 
                                                                INNER JOIN `students` 
                                                                    ON `students_has_intakes`.`students_student_id` = `students`.`student_id` 
                                                                INNER JOIN `login_details`
                                                                    ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                                INNER JOIN `intakes` 
                                                                    ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                                                                INNER JOIN `title` 
                                                                    ON `students`.`title_title_id` = `title`.`title_id`
                                                                INNER JOIN `status` 
                                                                    ON `students_has_intakes`.`status_status_id` = `status`.`status_id`
                                                                WHERE 
                                                                 `intakes_intake_id` = '" . $subjectData['intake_id'] . "' 
                                                                AND `students_has_intakes`.`status_status_id` = '1' 
                                                                    AND `students_student_id` NOT IN (
                                                                        SELECT `students_student_id` 
                                                                        FROM `students_has_subjects`
                                                                        INNER JOIN `subjects` 
                                                                            ON `students_has_subjects`.`subjects_subject_id` = `subjects`.`subject_id` 
                                                                        WHERE `subject_group_subject_group_id` = '" . $subjectData['subject_group_subject_group_id'] . "'
                                                                         
                                                                    )
                                                              
                                                                ";

                                if ($search_text != "") {
                                    $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%' OR `students_has_intakes`.`student_id` = '%" . $search_text . "%')";
                                }

                                if ($sort != 0) {
                                    if ($sort == 1) {
                                        $query .= " ORDER BY `first_name` ASC";
                                    } else if ($sort == 2) {
                                        $query .= " ORDER BY `first_name` DESC";
                                    } else if ($sort == 3) {
                                        $query .= " ORDER BY `students_has_intakes`.`student_id` DESC";
                                    } else if ($sort == 4) {
                                        $query .= " ORDER BY `students_has_intakes`.`student_id` ASC";
                                    }
                                }

                                if ($sort == 0) {
                                    $query .= "ORDER BY `students_has_intakes`.`student_id` DESC";
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
                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>
                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Email Address</th>
                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Mobile</th>
                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Admission Date</th>
                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Last Payment Status</th>
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
                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["sid"]; ?>" onchange="checkRowEvents(<?php echo $data['sid']; ?>);">
                                                            <?php
                                                            if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                            ?>
                                                                <img src="./assets/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
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
                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["student_id"]; ?></span></td>
                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>
                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["mobile"]; ?></span></td>
                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["addmission_date"]; ?></span></td>
                                                    <td class="pe-4 py-3 align-middle">
                                                        <?php
                                                        $status_classes = [
                                                            "0"     => "table-btn-purple",
                                                            "1"  => "table-btn-yellow",
                                                            "2"  => "table-btn-orange",
                                                            "3"  => "table-btn-success",
                                                            "4"  => "table-btn-danger",
                                                            "5"  => "table-btn-orange",
                                                            "6"  => "table-btn-success",
                                                            "7"  => "table-btn-danger",
                                                            "8"  => "table-btn-success",
                                                        ];
                                                        $sbutton_class = $status_classes[$data["last_payment_status_id"]];
                                                        ?>
                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["last_payment_status"]; ?></button>
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
