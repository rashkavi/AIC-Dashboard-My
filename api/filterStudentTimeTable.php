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

                    $student = isset($decoded['student']) ? $decoded['student'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    if (empty($student) || $student == 0) {
                        $status->error = 'Invalid Subejct Id';
                    } else {

                        $studentHasIntake = Database::search("SELECT
                `faculty`, `faculty_id`, `programme`, `programme_id`, `intake`, `intake_id`,
                `students_has_intakes`.`student_id`, `first_name`, `last_name`, `title`, `students_has_intakes_id`,
                `students_has_intakes`.`status_status_id`,`login_details`.`status_status_id` AS 'status_status_id_login_details'
            FROM `students_has_intakes`
            INNER JOIN `students`       ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
            INNER JOIN `login_details`  ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
            INNER JOIN `title`          ON `students`.`title_title_id` = `title`.`title_id`
            INNER JOIN `intakes`        ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes`     ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties`      ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $student . "'");

                        if ($studentHasIntake->num_rows == 0) {
                            $status->error = 'Invalid Subejct Id';
                        } else {
                            $studentHasIntakeData = $studentHasIntake->fetch_assoc();

                            $query = "
                                                                        SELECT 
                                                                            `tt`.`time_table_id`,
                                                                            `tt`.`lecture_title`,
                                                                            `tt`.`duration`,
                                                                            `tt`.`date_added`,
                                                                            `lt`.`lecture_type`,
                                                                            `lt`.`lecture_type_id`,
                                                                            `tts`.`time_table_status_id`,
                                                                            `tts`.`time_table_status`,
                                                                            `tt`.`date`,
                                                                            `tt`.`start_time`,
                                                                            `tt`.`end_time`,
                                                                            `u`.`user_fname`,
                                                                            `u`.`user_lname`,
                                                                            `t`.`title`,
                                                                            COUNT(DISTINCT `att`.`attachment_id`) AS `attachment_count`,
                                                                            COUNT(DISTINCT `rec`.`lecture_recording_lecture_recording_id`) AS `recording_count`,

                                                                            CASE
                                                                                WHEN CONCAT(`tt`.`date`, ' ', `tt`.`end_time`) >= NOW() THEN 1
                                                                                ELSE 2
                                                                            END AS `lecture_sort_group`

                                                                        FROM `time_table` AS `tt`

                                                                        INNER JOIN `lecture_type` AS `lt`
                                                                            ON `tt`.`lecture_type_lecture_type_id` = `lt`.`lecture_type_id`

                                                                        INNER JOIN `time_table_status` AS `tts`
                                                                            ON `tt`.`time_table_status_time_table_status_id` = `tts`.`time_table_status_id`

                                                                        INNER JOIN `lecturers` AS `lec`
                                                                            ON `tt`.`lecturers_lecturers_id` = `lec`.`lecturers_id`

                                                                        INNER JOIN `users` AS `u`
                                                                            ON `lec`.`users_user_id` = `u`.`user_id`

                                                                        INNER JOIN `title` AS `t`
                                                                            ON `u`.`title_title_id` = `t`.`title_id`

                                                                        INNER JOIN `subjects` AS `sub`
                                                                            ON `tt`.`subjects_subject_id` = `sub`.`subject_id`

                                                                        INNER JOIN `levels` AS `l`
                                                                            ON `sub`.`levels_level_id` = `l`.`level_id`

                                                                        INNER JOIN `intakes` AS `i`
                                                                            ON `l`.`intakes_intake_id` = `i`.`intake_id`

                                                                        INNER JOIN `programmes` AS `p`
                                                                            ON `i`.`programmes_programme_id` = `p`.`programme_id`

                                                                        LEFT JOIN `attachments` AS `att`
                                                                            ON `tt`.`time_table_id` = `att`.`time_table_time_table_id`

                                                                        LEFT JOIN `lecture_recording_has_lectures` AS `rec`
                                                                            ON `tt`.`time_table_id` = `rec`.`time_table_time_table_id`

                                                                        WHERE  `tt`.`lecture_type_lecture_type_id` != '3'

                                                                        AND `i`.`intake_id`='" . $studentHasIntakeData['intake_id'] . "'

                                                                       
                                                                        ";

                            if (!empty($search_text)) {
                                $query .= " AND CONCAT(u.user_fname, ' ', u.user_lname) LIKE '%" . $search_text . "%' OR tt.lecture_title LIKE '%" . $search_text . "%' OR l.level LIKE '%" . $search_text . "%' OR sub.subject LIKE '%" . $search_text . "%'";
                            }

                            if (!empty($sort)) {
                                if ($sort == 1) {
                                    $query .= " AND `tt`.`date` > '" . date('Y-m-d') . "'";
                                } else  if ($sort == 2) {
                                    $query .= " AND `tt`.`date` < '" . date('Y-m-d') . "'";
                                } else
                                if ($sort == 3) {
                                    $query .= " AND `tt`.`time_table_status_time_table_status_id` = '2'";
                                } else
                                if ($sort == 3) {
                                    $query .= " AND `tt`.`time_table_status_time_table_status_id` = '3'";
                                }
                            }

                            $query .= " GROUP BY `tt`.`time_table_id`

                                                                        ORDER BY `tt`.`date`, `tt`.`start_time` ASC";

                            $results = Database::search($query);
                            $results_count = $results->num_rows;
                            $result_per_page = (int)$resultPerPage;

                            if ($results_count > $result_per_page) {
                                $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                            } else {
                                $number_of_pages = 1;
                            }

                            $page_results = ($pageno - 1) * $result_per_page;
                            $lectures = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");
?>
                            <table class="table bl-solid m-0 p-0 g-0" id="lecture-schedule-table">
                                <thead>
                                    <tr class="br-t-5">
                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                            <div class="d-flex flex-row align-items-center">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="subjectLecScheduletableCheckEvents()">
                                                <span class="text-14 main-text fw-medium mx-2">Lecture Title</span>
                                            </div>
                                        </th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Lecturer</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Lecture Type</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Duration</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Attachments</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Recordings</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header">
                                            <div class="d-flex flex-row align-items-center">
                                                <span class="text-14 main-text fw-medium">Status</span>
                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="user-table-data">
                                    <?php
                                    if ($lectures->num_rows > 0) {
                                        while ($data = $lectures->fetch_assoc()) {
                                            $lecture_status = [
                                                "1" => "table-btn-primary",
                                                "2"  => "table-btn-danger",
                                                "3" => "table-btn-purple",
                                                "4" => "table-btn-pink",
                                                "5" => "table-btn-yellow",
                                                "6" => "table-btn-success",
                                            ];
                                            $status = "Upcoming";
                                            $statusId = '1';
                                            if ($data["time_table_status_id"] != 1) {
                                                $status = $data['time_table_status'];
                                                $statusId = $data['time_table_status_id'];
                                            } else {
                                                $timezone = new DateTimeZone("Asia/Colombo");
                                                $today = new DateTime("now", $timezone);
                                                $todayDate = $today->format('Y-m-d');
                                                $nowTime = $today->format('H:i:s');

                                                $dataDate = (new DateTime($data["date"]))->format('Y-m-d');
                                                if ($dataDate === $todayDate) {
                                                    if ($data["start_time"] <= $nowTime && $data["end_time"] >= $nowTime) {
                                                        $status = "Ongoing";
                                                        $statusId = '5';
                                                    } else if ($data["end_time"] < $nowTime) {
                                                        $status = "Completed";
                                                        $statusId = '6';
                                                    } else {
                                                        $status = "Upcoming";
                                                    }
                                                } else if ($dataDate < $todayDate) {
                                                    $status = "Completed";
                                                    $statusId = '6';
                                                }
                                            }

                                            $status_button_class = $lecture_status[$statusId];

                                            $deleteEnabale = $statusId == 1 ? true : false;

                                            if (!$deleteEnabale) {
                                                $hasRecording = Database::search("SELECT * FROM `lecture_recording_has_lectures` WHERE `time_table_time_table_id`='" . $data['time_table_id'] . "'");
                                                if ($hasRecording->num_rows == 0) {
                                                    $addLecturerPayments = Database::search("SELECT * FROM `lecturer_payments_has_lectures` WHERE `time_table_time_table_id`='" . $data['time_table_id'] . "'");
                                                    if ($addLecturerPayments->num_rows == 0) {
                                                        $deleteEnabale = true;
                                                    }
                                                }
                                            }
                                    ?>
                                            <tr>
                                                <td class="px-4 py-3 pe-0 align-middle">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                        <input type="checkbox"
                                                            class="form-check-input text-20 mt-0 mb-0 me-2 row-check"
                                                            value="<?= $data['time_table_id'] ?>"
                                                            <?= $deleteEnabale ? "" : "disabled" ?>>
                                                        <span class="text-14 main-text mx-2"><?php echo $data["lecture_title"] ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 pe-0 align-middle">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                        <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 align-middle">
                                                    <?php
                                                    if ($data["lecture_type_id"] == 1) {
                                                    ?>
                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                            <div class="custom-round-div bg-light-green border-white d-flex flex-row align-items-center justify-content-center">
                                                                <iconify-icon icon="material-symbols:home-work" width="15" height="15" class="text-dark-green m-0 p-0 g-0"></iconify-icon>
                                                            </div>
                                                            <span class="text-14 text-dark-green fw-medium px-3">Physical</span>
                                                        </div>
                                                    <?php
                                                    } else if ($data["lecture_type_id"] == 2) {
                                                    ?>
                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                            <div class="custom-round-div bg-light-purple border-white d-flex flex-row align-items-center justify-content-center">
                                                                <iconify-icon icon="wpf:online" width="15" height="15" class="text-dark-purple m-0 p-0 g-0"></iconify-icon>
                                                            </div>
                                                            <span class="text-14 text-dark-purple fw-medium px-3">Online</span>
                                                        </div>
                                                    <?php
                                                    } else if ($data["lecture_type_id"] == 3) {
                                                    ?>
                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                            <div class="custom-round-div bg-light-orange border-white d-flex flex-row align-items-center justify-content-center">
                                                                <iconify-icon icon="mdi:video" width="15" height="15" class="text-dark-orange m-0 p-0 g-0"></iconify-icon>
                                                            </div>
                                                            <span class="text-14 text-dark-orange fw-medium px-3">Recording</span>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </td>

                                                <?php
                                                $start = (new DateTime($data["start_time"]))->format('h:i A');
                                                $end = (new DateTime($data["end_time"]))->format('h:i A');
                                                $date = $data["date"] . " " . $start . " - " . $end;
                                                ?>
                                                <td class="px-4 py-2 align-middle">
                                                    <div class="d-flex flex-column">
                                                        <span class="text-14 main-text"><?php echo $data["date"] ?></span>
                                                        <span class="text-14 sub-text"><?php echo $start ?> - <?php echo $end ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data['duration'] ?></span></td>
                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data['attachment_count'] ?></span></td>
                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data['recording_count'] ?></span></td>
                                                <td class="px-4 py-3 align-middle">
                                                    <button class="table-btn <?php echo $status_button_class; ?> w-100"><?php echo $status; ?></button>
                                                </td>

                                            </tr>
                                        <?php
                                        }
                                    } else {
                                        ?>
                                        <tr class="justify-content-center align-middle">
                                            <td colspan="13" class="align-middle justify-content-center">
                                                <div class="d-flex flex-column align-items-center">
                                                    <img src="./assets/imgs/common/no-data.png" class="w-25 h-auto" alt="No data" class="empty-icon" />
                                                </div>
                                            </td>
                                        </tr>
                                    <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme" id="pagination-container">
                                <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                    <div class="row p-4 align-items-center">
                                        <!-- Previous Button (col-1) -->
                                        <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme Previous-button" onclick="handleStudentTimeTablePagination(<?= $student ?>,'previousPage')">
                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                        </button>
                                        <!-- Page Numbers (col-10, centered) -->
                                        <div class="col-10 custom-pagination d-flex justify-content-center">
                                            <ul class="pagination-list d-flex mb-0">
                                                <?php
                                                $shudderPages1 = false;
                                                $shudderPages2 = false;

                                                function renderPageButton($student, $i, $isActive = false)
                                                {
                                                    $activeClass = $isActive ? 'active' : '';
                                                    $action = $isActive ? '' : "onclick=\"handleStudentTimeTablePagination('{$student}','goToPage', '{$i}')\"";
                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                }

                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                        renderPageButton($student, $i, $pageno == $i);
                                                    } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                        $shudderPages1 = true;
                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text-color m-0 p-0'></iconify-icon></li>";
                                                    } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                        $shudderPages2 = true;
                                                        echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text-color m-0 p-0'></iconify-icon></li>";
                                                    }
                                                }
                                                ?>
                                            </ul>
                                        </div>
                                        <!-- Next Button (col-1) -->
                                        <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentTimeTablePagination(<?= $student ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                            <span class="text-14 main-text fw-semibold">Next</span>
                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="darkColor px-1"></iconify-icon>
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
