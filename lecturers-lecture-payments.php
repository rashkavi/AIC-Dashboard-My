<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('payments'))) {
            header("Location: ./dashboard");
        } else {

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
        GROUP BY 
            tt.date,
            tt.start_time,
            tt.end_time,
            users.user_id
            
        ORDER BY tt.date DESC";

            $results = Database::search($query);
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Lecturers Lecture Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "./includes/side-menu.php";
                            load_side_menu::load("Payments");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Payments");
                                    ?>
                                </div>
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-table.php"; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 bg-content-body contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">Lecturers Lecture Payments</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./payments';">Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./lecturers-payments';">Lecturers Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./lecturers-lecture-payments';">Lecturers Lecture Payments</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <span class="text-14 dark-theme fw-medium"><?= $results->num_rows ?> Payments</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="user-search-text" onkeyup="handleLecturersLecturePaymentsPagination('filter')">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>

                                                        <div class="collapse" id="filter">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row justify-content-end">
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" aria-label="Default select example" id="lecturer-selector" onchange="handleLecturersLecturePaymentsPagination('filter')">
                                                                                <option selected value="0">- Select Lecturer -</option>
                                                                                <?php
                                                                                $lecturers = Database::search("SELECT * FROM `users` 
                                                                                INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
                                                                                INNER JOIN `lecturers` ON `lecturers`.`users_user_id` = `users`.`user_id`
                                                                                WHERE `user_roles_user_role_id`='7' GROUP BY `users`.`user_id`");

                                                                                if ($lecturers->num_rows > 0) {
                                                                                    while ($lecturer = $lecturers->fetch_assoc()) {
                                                                                ?>
                                                                                        <option value="<?= $lecturer['user_id'] ?>"><?= $lecturer['title'] . '.' . $lecturer['user_fname'] . ' ' . $lecturer['user_lname'] ?></option>
                                                                                <?php
                                                                                    }
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <input type="text"
                                                                                class="form-control custom-input"
                                                                                id="from-date-selector"
                                                                                placeholder="From Date"
                                                                                onfocus="this.type='date'"
                                                                                onblur="if(!this.value) this.type='text'"
                                                                                onchange="handleLecturersLecturePaymentsPagination('filter')">
                                                                        </div>

                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <input type="text"
                                                                                class="form-control custom-input"
                                                                                id="to-date-selector"
                                                                                placeholder="To Date"
                                                                                onfocus="this.type='date'"
                                                                                onblur="if(!this.value) this.type='text'"
                                                                                onchange="handleLecturersLecturePaymentsPagination('filter')">
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleLecturersLecturePaymentsPagination('filter')">
                                                                                <option selected value="0">- Filter Data -</option>
                                                                                <option value="1">Pending</option>
                                                                                <option value="2">Scheduled</option>
                                                                                <option value="3">Cancelled</option>
                                                                                <option value="4">Completed</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>


                                                        <div class="d-none d-lg-block col-6"></div>
                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleLecturersLecturePaymentsPagination('filter')">
                                                                <option value="100" selected>100</option>
                                                                <option value="200">200</option>
                                                                <option value="300">300</option>
                                                                <option value="400">400</option>
                                                                <option value="500">500</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="table-responsive-wrapper">
                                                            <div class="table-responsive" id="users-table-container">
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
                                                                        $pageno = 1;
                                                                        $results_count = $results->num_rows;
                                                                        $result_per_page = 100;

                                                                        if ($results_count > $result_per_page) {
                                                                            $number_of_pages = ceil($results_count / $result_per_page);
                                                                        } else {
                                                                            $number_of_pages = 1;
                                                                        }

                                                                        $page_results = ($pageno - 1) * $result_per_page;
                                                                        $payments = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                        if ($payments->num_rows > 0) {
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
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersLecturePaymentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                                <span class="text-14 main-text fw-semibold">Next</span>
                                                                                <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                            </button>
                                                                        </div>
                                                                    </nav>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1"></div>
                                    <footer>
                                        <?php include './includes/footer.php' ?>
                                    </footer>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="cancelScheduleModal" tabindex="-1"
                    aria-labelledby="cancelScheduleModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">

                            <div class="d-flex justify-content-end pe-3 pt-3">
                                <button type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>

                            <div class="modal-body text-center p-4">

                                <div class="mb-3">
                                    <iconify-icon
                                        icon="mdi:alert-circle-outline"
                                        width="48"
                                        height="48"
                                        class="text-danger">
                                    </iconify-icon>
                                </div>

                                <h5 class="fw-semibold mb-2" id="cancelScheduleModalTitle">
                                    Cancel Schedule
                                </h5>

                                <p class="text-muted mb-3" id="cancelScheduleModalMessage">
                                    Are you sure you want to cancel this scheduled lecture payment? This action cannot be undone.
                                </p>

                                <div class="alert alert-warning text-start small mb-4" id="cancelScheduleDetails">
                                </div>

                                <div class="d-flex justify-content-center gap-3">
                                    <button type="button"
                                        class="btn btn-outline-secondary px-4 br-0"
                                        data-bs-dismiss="modal">
                                        No, Keep It
                                    </button>

                                    <button type="button"
                                        id="confirmCancelSchedule"
                                        class="btn btn-danger px-4 br-0">
                                        <iconify-icon icon="mdi:cancel" class="me-1"></iconify-icon>
                                        Yes, Cancel Schedule
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="rescheduleConfirmModal" tabindex="-1"
                    aria-labelledby="rescheduleConfirmModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow">

                            <div class="d-flex justify-content-end pe-3 pt-3">
                                <button type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                            </div>

                            <div class="modal-body text-center p-4">

                                <div class="mb-3">
                                    <iconify-icon
                                        icon="mdi:calendar-refresh-outline"
                                        width="48"
                                        height="48"
                                        class="text-primary">
                                    </iconify-icon>
                                </div>

                                <h5 class="fw-semibold mb-2" id="rescheduleConfirmModalTitle">
                                    Re-Schedule Confirmation
                                </h5>

                                <p class="text-muted mb-3" id="rescheduleConfirmModalMessage">
                                    Are you sure you want to re-schedule this lecture payment? The new date and time will replace the current schedule.
                                </p>

                                <div class="alert alert-info text-start small mb-4" id="rescheduleConfirmDetails">
                                </div>

                                <div class="d-flex justify-content-center gap-3">
                                    <button type="button"
                                        class="btn btn-outline-secondary px-4 br-0"
                                        data-bs-dismiss="modal">
                                        Cancel
                                    </button>

                                    <button type="button"
                                        id="confirmReschedule"
                                        class="btn btn-primary px-4 br-0">
                                        <iconify-icon icon="mdi:calendar-check-outline" class="me-1"></iconify-icon>
                                        Yes, Re-Schedule
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php
        }
    }
}
?>