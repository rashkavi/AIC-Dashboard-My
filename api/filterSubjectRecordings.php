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

                    require_once "../includes/connection.php";

                    $subject = isset($decoded['subject']) ? $decoded['subject'] : 0;
                    $lecturer = isset($decoded['lecturer']) ? $decoded['lecturer'] : 0;
                    $from_date = isset($decoded['from_date']) ? trim($decoded['from_date']) : '';
                    $to_date = isset($decoded['to_date']) ? trim($decoded['to_date']) : '';
                    $from_time = isset($decoded['from_time']) ? trim($decoded['from_time']) : '';
                    $to_time = isset($decoded['to_time']) ? trim($decoded['to_time']) : '';
                    $lec_type = isset($decoded['lec_type']) ? $decoded['lec_type'] : 0;
                    $filter = isset($decoded['filter']) ? $decoded['filter'] : 0;
                    $page = isset($decoded['page']) ? (int)$decoded['page'] : 1;
                    $resultPerPage = isset($decoded['result_per_page']) ? (int)$decoded['result_per_page'] : 10;

                    if (empty($subject) || $subject == 0) {
                        $status->error = 'Invalid Subejct Id';
                    } else {
                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                        $validLecturer = false;
                        $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $subject . "'");
                        if ($lecturerSubject->num_rows > 0) {
                            $validLecturer = true;
                        }

                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', $subject) || $validLecturer)) {
                            $status->error = 'You are not authorized to filter lectures.';
                        } else {

                            $query = "
                                                               SELECT 
                                                                   `lr`.`lecture_recording_id`,
                                                                  
                                                                   `s`.`subject`,
                                                                   `lr`.`lecture_recording`,
                                                                   `lr`.`recording_thumbnail`,
                                                                   `lr`.`expire_date`,
                                                                   `tt`.`date`,
                                                                   `tt`.`start_time`,
                                                                   `tt`.`end_time`
                                                               FROM `lecture_recording` AS `lr`
                                                               INNER JOIN `time_table` AS `tt`
                                                                   ON `lr`.`time_table_time_table_id` = `tt`.`time_table_id`
                                                               INNER JOIN `lecturers` AS `lec`
                                                                   ON `tt`.`lecturers_lecturers_id` = `lec`.`lecturers_id`
                                                               INNER JOIN `users` AS `u`
                                                                   ON `lec`.`users_user_id` = `u`.`user_id`
                                                               INNER JOIN `title` AS `t`
                                                                   ON `u`.`title_title_id` = `t`.`title_id`
                                                               INNER JOIN `subjects` AS `s`
                                                                   ON `lec`.`subjects_subject_id` = `s`.`subject_id`
                                                                WHERE `tt`.`subjects_subject_id`='" . $subject . "'
                                                           ";

                            if (!empty($from_date)) {
                                $query .= " AND `tt`.`date` >= '" . $from_date . "'";
                            }
                            if (!empty($to_date)) {
                                $query .= " AND `tt`.`date` <= '" . $to_date . "'";
                            }
                            if (!empty($from_time)) {
                                $query .= " AND `tt`.`start_time` >= '" . $from_time . "'";
                            }
                            if (!empty($to_time)) {
                                $query .= " AND `tt`.`end_time` <= '" . $to_time . "'";
                            }
                            if ($lecturer != 0) {
                                $query .= " AND `lec`.`lecturers_id` = '" . $lecturer . "'";
                            }
                            if ($lec_type != 0) {
                                $query .= " AND `tt`.`lecture_type_lecture_type_id` = '" . $lec_type . "'";
                            }
                            if ($filter != 0) {

                                $timezone = new DateTimeZone("Asia/Colombo");
                                $today = new DateTime("now", $timezone);
                                $todayDate = $today->format('Y-m-d');
                                $nowTime = $today->format('H:i:s');

                                if ($filter != 4 && $filter != 5) {
                                    if ($filter == 1) {
                                        $query .= " AND ((`tt`.`date` > '" . $todayDate . "') OR (`tt`.`date` = '" . $todayDate . "' AND `tt`.`start_time` >= '" . $nowTime . "')) AND `tt`.`time_table_status_time_table_status_id` NOT IN (4,5)";
                                    }
                                    if ($filter == 2) {
                                        $query .= " AND `tt`.`date` = '" . $todayDate . "' AND `tt`.`start_time` <= '" . $nowTime . "' AND `tt`.`end_time` >= '" . $nowTime . "' AND `tt`.`time_table_status_time_table_status_id` NOT IN (4,5)";
                                    }
                                    if ($filter == 3) {
                                        $query .= " AND `tt`.`date` < '" . $todayDate . "' AND `tt`.`time_table_status_time_table_status_id` NOT IN (4,5)";
                                    }
                                } else {
                                    $query .= " AND `tt`.`time_table_status_time_table_status_id` = '" . $filter . "'";
                                }
                            }

                            $query .= " ORDER BY `tt`.`time_table_id` DESC";

                            $results = Database::search($query);
                            $pageno = $page ? $page : 1;
                            $results_count = $results->num_rows;
                            $result_per_page = $resultPerPage;  // Consistent variable name

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
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                <span class="text-14 main-text fw-medium mx-2">Thumbnail</span>
                                            </div>
                                        </th>

                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Subject</th>
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecture Conduct Date Time</th>
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Recording Expire Date</th>
                                        <?php
                                        if (SessionUser::ifUserIsPro($sessionUserRole)  || UserPermissions::hasPermission('lecture_recording', 10, $id) || UserPermissions::hasPermission('lecture_recording', 11, $id)) {
                                        ?>
                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <?php
                                        }
                                        ?>

                                    </tr>
                                </thead>
                                <tbody id="user-table-data">
                                    <?php

                                    if ($lectures->num_rows > 0) {
                                        while ($data = $lectures->fetch_assoc()) {
                                    ?>
                                            <tr>
                                                <td class="px-4 py-3 pe-0 align-middle">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2">
                                                        <?php
                                                        if (!empty($data["recording_thumbnail"]) && file_exists('../assets/imgs/recording-thumbnail/' . $data["recording_thumbnail"])) {
                                                        ?>
                                                            <img src="./assets/imgs/recording-thumbnail/<?= $data["recording_thumbnail"] ?>" class="w-100 h-auto" style="max-width: 150px; max-height: 150px;" alt="recording thumbnail">
                                                        <?php
                                                        } else {

                                                        ?>
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto " style="max-width: 150px; max-height: 150px;" alt="recording thumbnail">
                                                        <?php
                                                        }
                                                        ?>

                                                    </div>
                                                </td>

                                                <td class="py-2 align-middle">
                                                    <span class="text-14 main-text"><?php echo $data["subject"]; ?></span>
                                                </td>
                                                <?php
                                                $start = (new DateTime($data["start_time"]))->format('h:i A');
                                                $end = (new DateTime($data["end_time"]))->format('h:i A');
                                                $date = $data["date"] . " " . $start . " - " . $end;
                                                ?>
                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $date ?></span></td>
                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data['expire_date'] ?></span></td>

                                                <?php
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('lecture_recording', 10, $id) || UserPermissions::hasPermission('lecture_recording', 11, $id)) {
                                                ?>
                                                    <td class="py-2 align-middle">
                                                        <button class="table-btn table-btn-dark-theme w-100" onclick="location.href='update-subject-recording?recording-id=<?php echo $data['lecture_recording_id']; ?>'">View</button>
                                                    </td>

                                                    <td class="py-3 align-middle">
                                                        <div class="dropdown">
                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer z-0" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5 z-1" aria-labelledby="dropdownMenuButton2">
                                                                <?php
                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('lecture_recording', 10, $id)) {
                                                                ?>
                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="update-subject-recording?recording-id=<?php echo $data['lecture_recording_id']; ?>">
                                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-2"></iconify-icon>
                                                                            Edit</a></li>
                                                                <?php
                                                                }

                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('lecture_recording', 10, $id)) {
                                                                ?>
                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="lecture-details?lecture-id=<?php echo $data['lecture_recording_id']; ?>">
                                                                            <iconify-icon icon="material-symbols:delete-outline-rounded" width="20" height="20" class="sub-text me-2"></iconify-icon>
                                                                            Remove</a></li>
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleSubjectRecordingsPagination(<?= $subject ?>,'previousPage')">
                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                        </button>
                                        <!-- Page Numbers (col-10, centered) -->
                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                            <ul class="pagination-list d-flex mb-0">
                                                <?php
                                                $shudderPages1 = false;
                                                $shudderPages2 = false;

                                                function renderPageButton($i, $isActive = false, $id)
                                                {
                                                    $activeClass = $isActive ? 'active' : '';
                                                    $action = $isActive ? '' : "onclick=\"handleSubjectRecordingsPagination({$id},'goToPage', '{$i}')\"";
                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                }

                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                        renderPageButton($i, $pageno == $i, $subject);
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleSubjectRecordingsPagination(<?= $subject ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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
