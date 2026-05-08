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

                    $subject = isset($decoded['subject']) ? $decoded['subject'] : 0;
                    $lecturer = isset($decoded['lecturer']) ? $decoded['lecturer'] : 0;
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
                                                                    `tt`.`time_table_id`,
                                                                    `lt`.`lecture_type`,
                                                                    `lt`.`lecture_type_id`,
                                                                    `tts`.`time_table_status_id`,
                                                                    `tts`.`time_table_status`,
                                                                    `tt`.`date`,
                                                                    `tt`.`duration`,
                                                                    `tt`.`start_time`,
                                                                    `tt`.`end_time`,
                                                                    `u`.`user_fname`,
                                                                    `u`.`user_lname`,
                                                                    `t`.`title`,
                                                                     `tt`.`lecture`
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
                                                                WHERE `tt`.`subjects_subject_id`='" . $subject . "'
                                                                AND `tt`.`lecture_type_lecture_type_id`='3'
                                                                
                                                                ";

                            if ($lecturer != 0) {
                                $query .= " AND `tt`.`lecturers_lecturers_id` = '" . $lecturer . "'";
                            }

                            if ($filter != 0) {
                                $query .= " AND `tt`.`time_table_status_time_table_status_id` = '" . $filter . "'";
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
                                                <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                            </div>
                                        </th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>

                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Duration</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Duration</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Satus</th>
                                        <?php
                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('lecture_schedule', 10, $subject)) {
                                        ?>
                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <?php
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody id="user-table-data">
                                    <?php
                                    if ($lectures->num_rows > 0) {
                                        while ($data = $lectures->fetch_assoc()) {
                                            $hours = floor($data["duration"] / 60);  // Get full hours
                                            $mins = $data["duration"] % 60;          // Get remaining minutes

                                            $data["duration"] =  ($hours > 0 ? "{$hours}h " : "") . ($mins > 0 ? "{$mins}min" : "");

                                            $iframeHtml = $data['lecture']; // this is the full <iframe> from DB
                                            $src = "";

                                            // Use regex to extract src
                                            if (preg_match('/src="([^"]+)"/', $iframeHtml, $matches)) {
                                                $src = $matches[1];
                                            }
                                    ?>
                                            <tr>
                                                <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                        <?php
                                                        if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
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

                                                <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data['duration'] ?></span></td>

                                                <td class="px-4 py-3 align-middle">
                                                    <?php
                                                    $lecture_status = [
                                                        "1" => "table-btn-success",
                                                        "2"  => "table-btn-danger",

                                                    ];
                                                    $status = "Unlocked";
                                                    $statusId = '1';
                                                    if ($data["time_table_status_id"] != 1) {
                                                        $status = "Locked";
                                                        $statusId = '2';
                                                    }

                                                    $status_button_class = $lecture_status[$statusId];
                                                    ?>
                                                    <button class="table-btn <?php echo $status_button_class; ?> w-100"><?php echo $status; ?></button>
                                                </td>
                                                <td class="px-4 py-2 align-middle">
                                                    <button class="table-btn table-btn-dark-theme" onclick="openLectureModal('<?= $src ?>')">View</button>
                                                </td>

                                                <?php
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('lecture_schedule', 10, $subject)) {
                                                ?>
                                                    <td class="px-4 py-3 align-middle">
                                                        <div class="dropdown">
                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer z-0" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5 z-1" aria-labelledby="dropdownMenuButton2">

                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-subject-pre-recorded-lecture?lecture-id=<?php echo $data['time_table_id']; ?>">
                                                                        <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-2"></iconify-icon>
                                                                        Edit</a></li>
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="subject-pre-recorded-lecture-attendance?lecture-id=<?php echo $data['time_table_id']; ?>">
                                                                        <iconify-icon icon="gg:check-o" width="20" height="20" class="sub-text me-2"></iconify-icon>
                                                                        Attendnace</a></li>
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleSubjectPrerecordedLecturesPagination(<?= $subject ?>,'previousPage')">
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
                                                    $action = $isActive ? '' : "onclick=\"handleSubjectPrerecordedLecturesPagination('{$id}','goToPage', '{$i}')\"";
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleSubjectPrerecordedLecturesPagination(<?= $subject ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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
