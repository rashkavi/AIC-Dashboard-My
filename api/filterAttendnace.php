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
                    $date = isset($decoded['date']) ? $decoded['date'] : 0;
                    $faculty = isset($decoded['faculty']) ? $decoded['faculty'] : 0;
                    $programme = isset($decoded['programme']) ? $decoded['programme'] : 0;
                    $intake = isset($decoded['intake']) ? $decoded['intake'] : 0;
                    $month = isset($decoded['month']) ? $decoded['month'] : '';
                    $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                    $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                    $update = isset($decoded['update']) ? $decoded['update'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $selectStudents = isset($decoded['selectStudents']) ? $decoded['selectStudents'] : [];
                    $unSelectStudents = isset($decoded['unSelectStudents']) ? $decoded['unSelectStudents'] : [];

                    $query = "
                    SELECT 
                        `students_has_intakes`.`student_id` AS 'sId', 
                        `first_name`, 
                        `last_name`, 
                        `email`, 
                        `mobile`, 
                        `whatsapp`, 
                        `students`.`profile_img`, 
                        `date_updated`, 
                        `title`.`title`, 
                        `student_in_out_status`.`student_in_out_status`,
                        `student_in_out_status_id`,
                       `users`.`user_fname`,
                       `users`.`user_lname`,
                       `users`.`profile_img` AS 'user_profile_img',
                       `title2`.`title` AS 'user_title'
                    FROM 
                        `student_in_out_records`
                     INNER JOIN `students_has_intakes` 
                        ON `student_in_out_records`.`students_has_intakes_students_has_intakes_id` = `students_has_intakes`.`students_has_intakes_id`
                    INNER JOIN 
                        `students` 
                        ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
            
                    INNER JOIN 
                        `login_details` 
                        ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                    INNER JOIN 
                        `title` 
                        ON `students`.`title_title_id` = `title`.`title_id`
                   
                    INNER JOIN 
                        `student_in_out_status` 
                        ON `student_in_out_records`.`student_in_out_status_student_in_out_status_id` = `student_in_out_status`.`student_in_out_status_id`
                    INNER JOIN
                             `users`
                             ON `student_in_out_records`.`users_user_id` = `users`.`user_id`
                    INNER JOIN 
                        `title` as `title2` 
                        ON `users`.`title_title_id` = `title2`.`title_id`
                    INNER JOIN `intakes` 
                        ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                    INNER JOIN `programmes` 
                        ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                ";

                    if ($search_text != "") {
                        $query .= " WHERE (`students_has_intakes`.`student_id` LIKE '%" . $search_text . "%' OR `first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR .`login_details`.`email` LIKE '%" . $search_text . "%' OR `students`.`mobile` LIKE '%" . $search_text . "%')";
                    }

                    if (!empty($faculty)) {
                        if (strpos($query, 'WHERE') === false) {
                            $query .= " WHERE `programmes`.`faculties_faculty_id`='" . $faculty . "'";
                        } else {
                            $query .= " AND `programmes`.`faculties_faculty_id`='" . $faculty . "'";
                        }
                    }

                    if (!empty($programme)) {
                        if (strpos($query, 'WHERE') === false) {
                            $query .= " WHERE `programmes`.`programme_id`='" . $programme . "'";
                        } else {
                            $query .= " AND `programmes`.`programme_id`='" . $programme . "'";
                        }
                    }

                    if (!empty($intake)) {
                        if (strpos($query, 'WHERE') === false) {
                            $query .= " WHERE `intakes`.`intake_id`='" . $intake . "'";
                        } else {
                            $query .= " AND `intakes`.`intake_id`='" . $intake . "'";
                        }
                    }

                    if (!empty($month)) {

                        $year = date('Y', strtotime($month));
                        $monthNum = date('m', strtotime($month));

                        if (strpos($query, 'WHERE') === false) {
                            $query .= " WHERE YEAR(`student_in_out_records`.`date_updated`) = '$year' 
                                        AND MONTH(`student_in_out_records`.`date_updated`) = '$monthNum'";
                        } else {
                            $query .= " AND YEAR(`student_in_out_records`.`date_updated`) = '$year' 
                                        AND MONTH(`student_in_out_records`.`date_updated`) = '$monthNum'";
                        }
                    }

                    if (!empty($date)) {
                        $date = date('Y-m-d', strtotime($date));
                        if (strpos($query, 'WHERE') === false) {
                            $query .= " WHERE DATE(`student_in_out_records`.`date_updated`) = '$date'";
                        } else {
                            $query .= " AND DATE(`student_in_out_records`.`date_updated`) = '$date'";
                        }
                    }

                    if ($sortByName != 0) {
                        $sort = $sortByName == 1 ? 'ASC' : 'DESC';
                        if (strpos($query, 'ORDER BY') === false) {
                            $query .= " ORDER BY `first_name` " . $sort;
                        } else {
                            $query .= ", `first_name` " . $sort;
                        }
                    }

                    if ($sortByDate != 0) {
                        $sort = $sortByDate == 1 ? 'ASC' : 'DESC';
                        if (strpos($query, 'ORDER BY') === false) {
                            $query .= " ORDER BY `students_has_intakes`.`student_id` " . $sort;
                        } else {
                            $query .= ", `students_has_intakes`.`student_id` " . $sort;
                        }
                    }

                    if ($sortByName == 0 && $sortByDate == 0) {
                        $query .= " ORDER BY `student_in_out_records`.`date_updated` DESC";
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
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                    <div class="d-flex flex-row align-items-center cursor-pointer">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleAttendnacePagination('sortByName')">
                                            <span class="text-14 main-text fw-medium mx-2">Student Name</span>

                                            <?php
                                            if ($sortByName == 0) {
                                            ?>
                                                <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                            <?php
                                            } else {
                                            ?>
                                                <iconify-icon icon="<?= $sortByName == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Student ID</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleAttendnacePagination('sortByDate')">
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <span class="text-14 main-text fw-medium">Date Time</span>
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
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Updated By</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php

                            if ($students->num_rows > 0) {
                                while ($data = $students->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["sId"]; ?>" onchange="checkRowEvents(<?php echo $data['sId']; ?>);">
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['sId']; ?>)">
                                                    <?php
                                                    if (!empty($data["profile_img"]) && file_exists('../assets/imgs/students/' . $data["profile_img"])) {
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
                                            </div>
                                        </td>

                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["sId"]; ?></span></td>
                                        <td class="py-2 align-middle">
                                            <?php
                                            $status_classes = [
                                                "1"     => "table-btn-success",
                                                "2"  => "table-btn-danger",
                                            ];
                                            $sbutton_class = $status_classes[$data["student_in_out_status_id"]];
                                            ?>
                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["student_in_out_status"]; ?></button>
                                        </td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["date_updated"])); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span></td>
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleAttendnacePagination('previousPage');">
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
                                            $action = $isActive ? '' : "onclick=\"handleAttendnacePagination('goToPage', '{$i}')\"";
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleAttendnacePagination('nextPage',<?= $number_of_pages ?>);">
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
