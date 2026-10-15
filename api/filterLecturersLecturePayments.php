<?php
$status = new stdClass();
require_once "../includes/connection.php";
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
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
                    $lecturer = isset($decoded['lecturer']) ? $decoded['lecturer'] : 0;
                    $to = isset($decoded['to']) ? $decoded['to'] : date('Y-m-d');
                    $from = isset($decoded['from']) ? $decoded['from'] : date('Y-m-d');
                    $user_sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $query = "SELECT 
            tt.time_table_id,
            tt.date,
            tt.start_time,
            tt.end_time,
            tt.lecturers_lecturers_id,
            title.title,
            users.user_fname,
            users.user_lname,
            users.profile_img,
            lecture_type.lecture_type,
              lecture_type.lecture_type_id,
            tt.lecture_title,
            tt.duration,
            COUNT(tt.subjects_subject_id) AS batch_count,
            `lecturer_payments`.`earning_status_earning_status_id`,
            `lecturer_payments`.`amount`,
            `earning_status`.`earning_status`,
            `programmes`.`programme`,
            `recording_count`.`recording_count`
        FROM time_table AS tt
        INNER JOIN `lecturers`  ON `lecturers`.`lecturers_id` = `tt`.`lecturers_lecturers_id`
        INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
        INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
        INNER JOIN `lecture_type` ON `lecture_type`.`lecture_type_id` = `tt`.`lecture_type_lecture_type_id`
        INNER JOIN `subjects` ON `subjects`.`subject_id` = `tt`.`subjects_subject_id`
        INNER JOIN `levels` ON `levels`.`level_id` = `subjects`.`levels_level_id`
        INNER JOIN `intakes` ON `intakes`.`intake_id` = `levels`.`intakes_intake_id`
        INNER JOIN `programmes` ON `programmes`.`programme_id` = `intakes`.`programmes_programme_id`
        LEFT JOIN `lecturer_payments_has_lectures` ON `lecturer_payments_has_lectures`.`time_table_time_table_id` = `tt`.`time_table_id`
        LEFT JOIN `lecturer_payments` ON `lecturer_payments`.`lecturer_payments_id` = `lecturer_payments_has_lectures`.`lecturer_payments_lecturer_payments_id`
        LEFT JOIN `earning_status` ON `earning_status`.`earning_status_id` = `lecturer_payments`.`earning_status_earning_status_id`
        LEFT JOIN (
            SELECT `time_table_time_table_id`, COUNT(`time_table_time_table_id`) AS recording_count
            FROM `lecture_recording_has_lectures`
            GROUP BY `time_table_time_table_id`
        ) AS `recording_count` ON `recording_count`.`time_table_time_table_id` = `tt`.`time_table_id`
       
        WHERE tt.time_table_status_time_table_status_id = '1'
        AND tt.lecture_type_lecture_type_id != 3
        AND CONCAT(tt.date , ' ', tt.start_time) <= '" . date('Y-m-d H:i:s') . "'
       ";

                    if ($search_text != "") {
                        $query .= " AND (`user_fname` LIKE '%" . $search_text . "%' 
                        OR `user_lname` LIKE '%" . $search_text . "%' 
                        OR `user_email` LIKE '%" . $search_text . "%' 
                        OR CONCAT(`user_fname`, ' ', `user_lname`) LIKE '%" . $search_text . "%'
                        OR `user_mobile` LIKE '%" . $search_text . "%')";
                    }

                    if ($lecturer != 0) {
                        $query .= " AND users.user_id = " . $lecturer;
                    }

                    if ($to != "" && $from != "") {
                        $query .= " AND tt.date BETWEEN '" . $from . "' AND '" . $to . "' ";
                    } else if ($to != "") {
                        $query .= " AND tt.date <= '" . $to . "' ";
                    } else if ($from != "") {
                        $query .= " AND tt.date >= '" . $from . "' ";
                    }

                    if ($user_sort != 0) {
                        if ($user_sort == 1) {
                            $query .= " AND `lecturer_payments`.`earning_status_earning_status_id` IS NULL";
                        }
                        if ($user_sort == 2) {
                            $query .= " AND `lecturer_payments`.`earning_status_earning_status_id` = 1";
                        }
                        if ($user_sort == 3) {
                            $query .= " AND `lecturer_payments`.`earning_status_earning_status_id` = 3";
                        }
                        if ($user_sort == 4) {
                            $query .= " AND `lecturer_payments`.`earning_status_earning_status_id` = 2";
                        }
                    }

                    $query .= "  GROUP BY 
            tt.date,
            tt.start_time,
            tt.end_time,
            users.user_id
        ORDER BY tt.date DESC";

                    $results = Database::search($query);
                    $results_count = $results->num_rows;
                    $result_per_page = (int)$resultPerPage;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $payments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                    function formatDuration($minutes)
                    {
                        $hours = floor($minutes / 60);
                        $mins = $minutes % 60;

                        $result = "";

                        if ($hours > 0) {
                            $result .= $hours . " h ";
                        }

                        if ($mins > 0) {
                            $result .= $mins . " min";
                        }

                        return trim($result);
                    }

?>
                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                        <thead>
                            <tr class="br-t-5">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                    <div class="d-flex flex-row align-items-center">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <span class="text-14 main-text fw-medium mx-2">Date Time</span>
                                    </div>
                                </th>

                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecturer Name</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Programme</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecture Type</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecture Title</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Live Rec Uploaded</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Scheduled Duartion</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Concurrent Batches</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Schedule Amt to Pay</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Payment Status</th>
                                <th scope="col" class="pe-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php

                            if ($payments->num_rows > 0) {

                                while ($data = $payments->fetch_assoc()) {
                            ?>
                                    <tr>
                                        <td class="px-4 pe-0 py-3 pe-0 align-middle">
                                            <div class="d-flex flex-row align-items-center p-0 g-0 m-0">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                <p class="text-14 main-text mx-2"><?= $data['date'] ?></br><?= date("h:i A", strtotime($data['start_time'])) ?> - <?= date("h:i A", strtotime($data['end_time'])) ?></p>
                                            </div>
                                        </td>
                                        <td class="py-2 align-middle">
                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
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
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["programme"]; ?></span></td>
                                        <td class="py-2 align-middle">
                                            <?php
                                            $status_classes = [
                                                "1"     => "table-btn-primary",
                                                "2"  => "table-btn-yellow",
                                            ];
                                            $sbutton_class = $status_classes[$data["lecture_type_id"]];
                                            ?>
                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["lecture_type"]; ?></button>
                                        </td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["lecture_title"]; ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["recording_count"]; ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= formatDuration($data["duration"]); ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["batch_count"]; ?></span></td>
                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["amount"] != null ? number_format($data["amount"]) : ''; ?></span></td>

                                        <td class="py-2 align-middle">
                                            <?php
                                            $status_classes = [
                                                "0"     => "table-btn-yellow",
                                                "1"  => "table-btn-pink",
                                                "2"  => "table-btn-success",
                                                "3"  => "table-btn-danger",
                                            ];
                                            $sbutton_class = $status_classes[$data["earning_status_earning_status_id"] == '' ? 0 : $data["earning_status_earning_status_id"]];
                                            ?>
                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["earning_status_earning_status_id"] == '' ? "Pending Approval" : $data['earning_status']; ?></button>
                                        </td>
                                        <td class="py-3 pe-4 align-middle">
                                            <div class="dropdown">
                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="lecturer-lecture-payment?lecture-id=<?php echo $data['time_table_id']; ?>">
                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                            View</a></li>

                                                    <?php

                                                    if ($data["earning_status_earning_status_id"] == 1) {
                                                    ?>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                onclick="openCancelScheduleModal('<?= $data['time_table_id']; ?>', '<?= htmlspecialchars($data['title'] . '.' . $data['user_fname'] . ' ' . $data['user_lname'], ENT_QUOTES); ?>', '<?= $data['date']; ?>')">
                                                                <iconify-icon icon="ic:twotone-cancel" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                Cancel Schedule</a></li>
                                                    <?php
                                                    }
                                                    if ($data["earning_status_earning_status_id"] == 3) {
                                                    ?>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                onclick="openReScheduleModal('<?= $data['time_table_id']; ?>', '<?= htmlspecialchars($data['title'] . '.' . $data['user_fname'] . ' ' . $data['user_lname'], ENT_QUOTES); ?>', '<?= $data['date']; ?>')">
                                                                <iconify-icon icon="ci:arrows-reload-01" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                Re-Schedule</a></li>
                                                    <?php
                                                    }
                                                    ?>
                                                </ul>
                                            </div>
                                        </td>
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLecturersLecturePaymentsPagination('previousPage')">
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
                                            $action = $isActive ? '' : "onclick=\"handleLecturersLecturePaymentsPagination('goToPage', '{$i}')\"";
                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                        }

                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                renderPageButton($i, $pageno == $i);
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersLecturePaymentsPagination('nextPage','<?php echo $pageno ?>','<?= $number_of_pages ?>')">
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


?>