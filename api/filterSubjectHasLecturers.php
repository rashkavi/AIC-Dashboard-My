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

                    $subject = isset($decoded['id']) ? $decoded['id'] : 0;
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                    $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                    $update = isset($decoded['update']) ? $decoded['update'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                    $selectLecturers = isset($decoded['checkedLecturers']) ? $decoded['checkedLecturers'] : [];
                    $unSelectLecturers = isset($decoded['uncheckedLecturers']) ? $decoded['uncheckedLecturers'] : [];

                    if (empty($subject) || $subject == 0) {
                        $status->error = 'Invalid Subejct Id';
                    } else {
                        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                        $validLecturer = false;
                        $lecturerSubject = Database::search("SELECT * FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "' AND `subjects_subject_id`='" . $subject . "'");
                        if ($lecturerSubject->num_rows > 0) {
                            $validLecturer = true;
                        }

                        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('subject_lecturers', $subject) || $validLecturer)) {
                            $status->error = 'You are not authorized to filter lectures.';
                        } else {

                            $query = " SELECT 
                                                                   `title`,
                                                                   `user_id`,
                                                                   `user_fname`,
                                                                   `user_lname`,
                                                                   `profile_img`,
                                                                   `subject_id`,
                                                                   `subject`,
                                                                   `max_course_hours`,
                                                                   `status`,
                                                                   `status_id`,
                                                                   `lecturers_id`,
                                                                     `hourly_rate`,
                                                                   `per_examination_fee`,
                                                                   `per_assignment_fee`,
                                                                   `lecturers`.`added_at`
                                                               FROM `lecturers`
                                                               INNER JOIN `subjects` 
                                                                   ON `lecturers`.`subjects_subject_id` = `subjects`.`subject_id`
                                                               INNER JOIN `users` 
                                                                   ON `lecturers`.`users_user_id` = `users`.`user_id`
                                                               INNER JOIN `user_roles` 
                                                                   ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                                                               INNER JOIN `title` 
                                                                   ON `users`.`title_title_id` = `title`.`title_id`
                                                               INNER JOIN `status` 
                                                                   ON `lecturers`.`status_status_id` = `status`.`status_id`
                                                               WHERE `subjects`.`subject_id`='" . $subject . "'
                                            ";

                            if ($search_text != "") {
                                $query .= " AND (`user_fname` LIKE '%" . $search_text . "%' OR `user_lname` LIKE '%" . $search_text . "%' OR `subject` LIKE '%" . $search_text . "%')";
                            }

                            if ($sort != 0) {
                                if (strpos($query, 'WHERE') == false) {
                                    $query .= " WHERE `lecturers`.`status_status_id` = '" . $sort . "'";
                                } else {
                                    $query .= " AND `lecturers`.`status_status_id` = '" . $sort . "'";
                                }
                            }

                            if ($sortByName != 0) {
                                $sort = $sortByName == 1 ? 'ASC' : 'DESC';
                                if (strpos($query, 'ORDER BY') === false) {
                                    $query .= " ORDER BY `user_fname` " . $sort;
                                } else {
                                    $query .= ", `user_fname` " . $sort;
                                }
                            }

                            if ($sortByDate != 0) {
                                $sort = $sortByDate == 1 ? 'ASC' : 'DESC';
                                if (strpos($query, 'ORDER BY') === false) {
                                    $query .= " ORDER BY `lecturers`.`added_at` " . $sort;
                                } else {
                                    $query .= ", `lecturers`.`added_at` " . $sort;
                                }
                            }

                            if ($sortByName == 0 && $sortByDate == 0) {
                                $query .= " ORDER BY `lecturers_id` DESC";
                            }

                            $isUpdateStudentStatus = $update == 1 ? true : ($update == 2 ? true : false);

                            $results = Database::search($query);
                            $results_count = $results->num_rows;
                            $result_per_page = $resultPerPage;

                            if ($results_count > $result_per_page) {
                                $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                            } else {
                                $number_of_pages = 1;
                            }

                            $page_results = ($pageno - 1) * $result_per_page;
                            $lecturers = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                            <table class="table bl-solid m-0 p-0 g-0" id="lecturers-table">
                                <thead>
                                    <tr class="br-t-5">
                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                            <div class="d-flex flex-row align-items-center">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="lecturers-check-all" onchange="lecturersTableCheckEvents()">
                                                <div class="d-flex justify-content-between align-items-center w-100" onclick="handleLecturersPagination('<?= $subject ?>','sortByName')">
                                                    <span class="text-14 main-text fw-medium mx-2">Lecturer Name</span>
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
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleLecturersPagination('<?= $subject ?>','sortByDate')">
                                            <div class="d-flex justify-content-between align-items-center cursor-pointer w-100">
                                                <span class="text-14 main-text fw-medium me-2">Date Added</span>
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
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Completed Lecturing Hours</th>

                                        <th scope="col" class="py-3 bg-table-header align-content-center">
                                            <div class="d-flex flex-row align-items-center">
                                                <span class="text-14 main-text fw-medium">Status</span>
                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            </div>
                                        </th>
                                        <?php
                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 1, $subject) || $validLecturer) {
                                        ?>
                                            <th scope="col" class="pe-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center">Action</th>
                                        <?php
                                        }
                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 10, $subject)) {
                                        ?>
                                            <th scope="col" class="pe-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                        <?php
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody id="user-table-data">
                                    <?php
                                    if ($lecturers->num_rows > 0) {
                                        while ($data = $lecturers->fetch_assoc()) {
                                            $name = $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"];
                                    ?>
                                            <tr>
                                                <td class="px-4 py-3 pe-0 align-middle">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">

                                                        <?php
                                                        $isMatchSelectUpdate = false;
                                                        if ($isUpdateStudentStatus) {
                                                            if (count($selectLecturers) > 0) {
                                                                if (in_array($data['lecturers_id'], $selectLecturers)) {
                                                                    if ($data['status_id'] == 1 && $update == 2) {
                                                                        $isMatchSelectUpdate = true;
                                                                    } else if ($data['status_id'] == 2 && $update == 1) {
                                                                        $isMatchSelectUpdate = true;
                                                                    }
                                                                }
                                                            }

                                                            if (count($unSelectLecturers) > 0) {
                                                                if (in_array($data['lecturers_id'], $unSelectLecturers)) {
                                                                    $isMatchSelectUpdate = false;
                                                                }
                                                            }

                                                            if (count($selectLecturers) == 0 && count($unSelectLecturers) == 0) {
                                                                if ($data['status_id'] == 1 && $update == 2) {
                                                                    $isMatchSelectUpdate = true;
                                                                } else if ($data['status_id'] == 2 && $update == 1) {
                                                                    $isMatchSelectUpdate = true;
                                                                }
                                                            }
                                                        }

                                                        if ($isMatchSelectUpdate) {
                                                        ?>
                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 lecturers-row-check" checked id="check-lecturer<?= $data['lecturers_id']; ?>" onchange="lecturerCheckRowEvents(<?= $data['lecturers_id']; ?>);">
                                                        <?php
                                                        } else {
                                                        ?>
                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 lecturers-row-check" id="check-lecturer<?= $data['lecturers_id']; ?>" onchange="lecturerCheckRowEvents(<?= $data['lecturers_id']; ?>);">
                                                        <?php
                                                        }
                                                        if (!empty($data["profile_img"]) && file_exists('../assets/imgs/users/' . $data["profile_img"])) {
                                                        ?>
                                                            <img src="./assets/imgs/users/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                        <?php
                                                        } else {
                                                            $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                            $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                        ?>
                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                        <?php
                                                        }
                                                        ?>
                                                        <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                    </div>
                                                </td>

                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= date("Y-m-d h:i A", strtotime($data["added_at"])); ?> </span></td>
                                                <td class="py-2 align-middle">
                                                    <?php
                                                    $timezone = new DateTimeZone("Asia/Colombo");
                                                    $dateTime = new DateTime("now", $timezone);
                                                    $todayDate = $dateTime->format('Y-m-d');
                                                    $nowTime = $dateTime->format('H:i:s');

                                                    $result = Database::search("
                                                                                            SELECT 
                                                                                                CONCAT(
                                                                                                    LPAD(FLOOR(SUM(`duration`) / 60), 2, '0'), 
                                                                                                    ' hours ',
                                                                                                    LPAD(MOD(SUM(`duration`), 60), 2, '0'),
                                                                                                    ' min'
                                                                                                ) AS total_completed_hours
                                                                                            FROM `time_table`
                                                                                            WHERE `subjects_subject_id` = '" . $data['subject_id'] . "'
                                                                                                AND `lecturers_lecturers_id` = '" . $data['lecturers_id'] . "'
                                                                                                AND (
                                                                                                    (`date` = '" . $todayDate . "' 
                                                                                                        AND `start_time` <= '" . $nowTime . "' 
                                                                                                        AND `end_time` <= '" . $nowTime . "')
                                                                                                    OR `date` < '" . $todayDate . "'
                                                                                                )
                                                                                                AND `time_table`.`time_table_status_time_table_status_id` = '1'
                                                                                        ");

                                                    $row = $result->fetch_assoc();
                                                    ?>
                                                    <span class="text-14 main-text"><?= $row["total_completed_hours"]; ?></span>
                                                </td>
                                             
                                                <td class="py-2 align-middle">
                                                    <?php
                                                    $status_classes = [
                                                        "Active"     => "table-btn-success",
                                                        "Inactive"  => "table-btn-danger",
                                                    ];
                                                    $sbutton_class = $status_classes[$data["status"]];
                                                    ?>
                                                    <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["status"]; ?></button>
                                                </td>
                                                <?php
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 1, $subject)) {
                                                ?>
                                                    <td class="py-2 align-middle">
                                                        <button class="table-btn table-btn-dark-theme text-white w-100" onclick="window.location.href='lecturer-subject-activity.php?lecturer-id=<?= $data['lecturers_id']; ?>' ">View</button>
                                                    </td>
                                                    <?php
                                                } else if ($validLecturer) {
                                                    if ($sessionUserObject['user_id'] == $data['user_id']) {
                                                    ?>
                                                        <td class="py-2 align-middle">
                                                            <button class="table-btn table-btn-dark-theme text-white w-100" onclick="window.location.href='lecturer-subject-activity.php?lecturer-id=<?= $data['lecturers_id']; ?>' ">View</button>
                                                        </td>
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <td class="py-2 align-middle"></td>
                                                    <?php
                                                    }
                                                }
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 10, $id)) {
                                                    ?>
                                                    <td class="py-3 align-middle">
                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                        <div class="dropdown">
                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="openModelEditLecture('<?= $name ?>',<?= $data['hourly_rate'] ?>,<?= $data['per_assignment_fee'] ?>,<?= $data['per_examination_fee'] ?>,<?= $data['lecturers_id'] ?>)">
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
                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container2">
                                <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                    <div class="row p-4 align-items-center">
                                        <!-- Previous Button (col-1) -->
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLecturersPagination(<?= $subject ?>,'previousPage')">
                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                        </button>
                                        <!-- Page Numbers (col-10, centered) -->
                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                            <ul class="pagination-list d-flex mb-0">
                                                <?php
                                                $shudderPages1 = false;
                                                $shudderPages2 = false;

                                                function renderPageButton2($subject, $i, $isActive = false)
                                                {
                                                    $activeClass = $isActive ? 'active' : '';
                                                    $action = $isActive ? '' : "onclick=\"handleLecturersPagination('{$subject}','goToPage', '{$i}')\"";
                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                }

                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                        renderPageButton2($subject, $i, $pageno == $i);
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersPagination(<?= $subject ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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
