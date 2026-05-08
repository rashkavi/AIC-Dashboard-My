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

                    $lecture_id = isset($decoded['lecture_id']) ? $decoded['lecture_id'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    if ($lecture_id == 0) {
                        $status->error = 'Invalid Lecture Id';
                    } else {
                        $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));
                        $query = "
                                    SELECT 
                                        `tt`.`time_table_id`,
                                        `lt`.`lecture_type`,
                                        `lt`.`lecture_type_id`,
                                        `tts`.`time_table_status_id`,
                                        `tts`.`time_table_status`,
                                       
                                        `i`.`intake_id`,
                                      
                                        `s`.`subject_id`,
                                   
                                        `s`.`subject_type_subject_type_id`,
                                        `tt`.`date`,
                                        `tt`.`start_time`,
                                        `tt`.`end_time`
                                      
                                    FROM `time_table` AS `tt`
                                    
                                    INNER JOIN `lecture_type` AS `lt`
                                        ON `tt`.`lecture_type_lecture_type_id` = `lt`.`lecture_type_id`
                                    INNER JOIN `time_table_status` AS `tts`
                                        ON `tt`.`time_table_status_time_table_status_id` = `tts`.`time_table_status_id`  
                                                              
                                    INNER JOIN `subjects` AS `s`
                                        ON `tt`.`subjects_subject_id` = `s`.`subject_id`
                                    INNER JOIN `levels` AS `l`
                                        ON `s`.`levels_level_id` = `l`.`level_id`
                                    INNER JOIN `intakes` AS `i`
                                        ON `l`.`intakes_intake_id` = `i`.`intake_id`
                                 
                                    WHERE `tt`.`lecture_type_lecture_type_id` != 3
                                    AND `tt`.`time_table_status_time_table_status_id` = 1
                                    AND `tt`.`time_table_id` = '" . $lecture_id . "'
                                    AND `tt`.`date` = '" . $dateTime->format("Y-m-d") . "'
                                    AND `tt`.`start_time` < '" . $dateTime->format("H:i:s") . "'
                                    AND `tt`.`end_time` > '" . $dateTime->format("H:i:s") . "'
                                    ORDER BY `tt`.`time_table_id` DESC
                                ";
                        $lecture = Database::search($query);
                        if ($lecture->num_rows == 0) {
                            $status->error = 'Invalid Lecture Id';
                        } else {
                            $lectureData = $lecture->fetch_assoc();

                            $query = "SELECT `students`.`student_id` AS 'sId',`students_has_intakes`.`student_id` AS 'shId', `login_details`.`first_name`, `login_details`.`last_name`, `login_details`.`email`, `students`.`mobile`,`students`.`profile_img`,`title`.`title`, `status`.`status`";

                            if ($lectureData["lecture_type_id"] == 2) {
                                $query .= ", `attendance`.`attendance_id`,`attendance`.`attendance_status_attendance_status_id`";
                            }

                            $query .= "FROM `students` 
                                        INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                                        INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                                        INNER JOIN `status` ON `login_details`.`status_status_id`=`status`.`status_id`
                                        INNER JOIN `students_has_intakes` ON `students`.`student_id`=`students_has_intakes`.`students_student_id`
                                        ";

                            if ($lectureData["subject_type_subject_type_id"] == 2) {
                                $query .= " INNER JOIN `students_has_subjects` ON `students`.`student_id`=`students_has_subjects`.`students_student_id`
                                ";
                            }

                            if ($lectureData["lecture_type_id"] == 2) {
                                $query .= " INNER JOIN `attendance` ON (`students`.`student_id`=`attendance`.`students_student_id` AND `attendance`.`time_table_time_table_id`='" . $lectureData["time_table_id"] . "')";
                            }

                            $query .= " WHERE `students_has_intakes`.`intakes_intake_id`='" . $lectureData["intake_id"] . "' AND `students_has_intakes`.`status_status_id`='1'";

                            if ($lectureData["subject_type_subject_type_id"] == 2) {
                                $query .= " AND `students_has_subjects`.`subjects_subject_id`='" . $lectureData["subject_id"] . "' AND `students_has_subjects`.`status_status_id`='1'";
                            }

                            if ($search_text != "") {
                                $query .= " AND (`students`.`student_id` LIKE '%" . $search_text . "%' OR `first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%')";
                            }
                            if ($sort != 0) {
                                if ($sort == 1) {
                                    $query .= " ORDER BY `first_name` ASC";
                                } else if ($sort == 2) {
                                    $query .= " ORDER BY `first_name` DESC";
                                } else if ($sort == 3) {
                                    if ($lectureData["lecture_type_id"] == 2) {
                                        $query .= " AND `attendance`.`attendance_status_attendance_status_id` = 1";
                                    } else {
                                        $query .= " AND `students`.`student_id` NOT IN (SELECT `students_student_id` FROM `attendance` WHERE `time_table_time_table_id`='" . $lectureData["time_table_id"] . "')";
                                    }
                                } else if ($sort == 4) {
                                    if ($lectureData["lecture_type_id"] == 2) {
                                        $query .= " AND `attendance`.`attendance_status_attendance_status_id` = 2";
                                    } else {
                                        $query .= " AND `students`.`student_id` IN (SELECT `students_student_id` FROM `attendance` WHERE `time_table_time_table_id`='" . $lectureData["time_table_id"] . "' AND `attendance`.`attendance_status_attendance_status_id` = 2)";
                                    }
                                } else if ($sort == 5) {
                                    if ($lectureData["lecture_type_id"] == 2) {
                                        $query .= " AND `attendance`.`attendance_status_attendance_status_id` = 3";
                                    } else {
                                        $query .= " AND `students`.`student_id` IN (SELECT `students_student_id` FROM `attendance` WHERE `time_table_time_table_id`='" . $lectureData["time_table_id"] . "' AND `attendance`.`attendance_status_attendance_status_id` = 3)";
                                    }
                                }
                            }

                            if ($sort == 0 || $sort == 3 || $sort == 4 || $sort == 5) {
                                $query .= " ORDER BY `date_registered` DESC";
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
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Student Intake Id</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Email Address</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Mobile</th>

                                        <th scope="col" class="px-4 py-3 bg-table-header">
                                            <div class="d-flex flex-row align-items-center">
                                                <span class="text-14 main-text fw-medium">Attendance Status</span>
                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            </div>
                                        </th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
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
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["sId"]; ?>" onchange="checkRowEvents(<?php echo $data['sId']; ?>);">
                                                        <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['sId']; ?>)">
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
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["shId"]; ?></span></td>
                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>
                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["mobile"]; ?></span></td>
                                                <?php
                                                $attendStatus = "Pending";
                                                $attendanceId = 0;
                                                if ($lectureData["lecture_type_id"] == 1) {
                                                    $alreadyMarkAttendance = Database::search("SELECT `attendance_status_attendance_status_id`,`attendance_id` FROM `attendance` WHERE `students_student_id`='" . $data["sId"] . "' AND `time_table_time_table_id`='" . $lectureData["time_table_id"] . "'");
                                                    if ($alreadyMarkAttendance->num_rows > 0) {
                                                        $alreadyMarkAttendanceData = $alreadyMarkAttendance->fetch_assoc();
                                                        $attendanceId = $alreadyMarkAttendanceData["attendance_id"];
                                                        if ($alreadyMarkAttendanceData["attendance_status_attendance_status_id"] == 2) {
                                                            $attendStatus = "Present";
                                                        } else if ($alreadyMarkAttendanceData["attendance_status_attendance_status_id"] == 3) {
                                                            $attendStatus = "Absent";
                                                        }
                                                    }
                                                } else {
                                                    $attendanceId = $data["attendance_id"];
                                                    if ($data["attendance_status_attendance_status_id"] == 2) {
                                                        $attendStatus = "Present";
                                                    } else if ($data["attendance_status_attendance_status_id"] == 3) {
                                                        $attendStatus = "Absent";
                                                    }
                                                }
                                                ?>
                                                <td class="px-4 py-2 align-middle">
                                                    <?php
                                                    $status_classes = [
                                                        "Pending"     => "table-btn-yellow",
                                                        "Present"     => "table-btn-success",
                                                        "Absent"  => "table-btn-danger",
                                                    ];
                                                    $sbutton_class = $status_classes[$attendStatus];
                                                    ?>
                                                    <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $attendStatus ?></button>
                                                </td>
                                                <td class="px-4 py-2 align-middle">
                                                    <?php
                                                    if ($attendStatus == "Pending") {
                                                    ?>
                                                        <div class="d-flex gap-3 align-items-center justify-content-center">
                                                            <button class="table-btn table-btn-success active w-100" onclick="markAttendance(<?= $data['sId'] ?>,<?= $lectureData['time_table_id'] ?>,2)">Present</button>
                                                            <button class="table-btn table-btn-danger active w-100" onclick="markAttendance(<?= $data['sId'] ?>,<?= $lectureData['time_table_id'] ?>,3)">Absent</button>
                                                        </div>
                                                    <?php
                                                    } else if ($attendStatus == "Present") {
                                                    ?>
                                                        <button class="table-btn table-btn-danger active w-100" onclick="updateAttendance(<?= $attendanceId ?>,<?= $lecture_id ?>)">Absent</button>
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <button class="table-btn table-btn-success active w-100" onclick="updateAttendance(<?= $attendanceId ?>,<?= $lecture_id ?>)">Present</button>
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsOngoingLectureAttendancePagination(<?= $lecture_id ?>,'previousPage');">
                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                        </button>
                                        <!-- Page Numbers (col-10, centered) -->
                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                            <ul class="pagination-list d-flex mb-0">
                                                <?php
                                                $shudderPages1 = false;
                                                $shudderPages2 = false;

                                                function renderPageButton($i, $isActive = false, $lectureId)
                                                {
                                                    $activeClass = $isActive ? 'active' : '';
                                                    $action = $isActive ? '' : "onclick=\"handleStudentsOngoingLectureAttendancePagination({$lectureId},'goToPage', '{$i}')\"";
                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                }

                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                        renderPageButton($i, $pageno == $i, $lecture_id);
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsOngoingLectureAttendancePagination(<?= $lecture_id ?>,'nextPage',<?= $number_of_pages ?>);">
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
