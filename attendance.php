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
        ORDER BY 
            `student_in_out_records`.`date_updated` DESC
    ";

        $results = Database::search($query);

        $students = Database::search("
        SELECT 
            students.student_id,
            title.title,
            first_name,
            last_name,
            email,
            mobile,
            whatsapp,
            profile_img,
            students_has_intakes.student_id,
            programmes.programme,
            nic,
            passport
         
        FROM students_has_intakes
        INNER JOIN students 
            ON students_has_intakes.students_student_id = students.student_id
        INNER JOIN login_details 
            ON students.login_details_login_details_id = login_details.login_details_id
        INNER JOIN title 
            ON students.title_title_id = title.title_id
        INNER JOIN intakes 
            ON students_has_intakes.intakes_intake_id = intakes.intake_id
        INNER JOIN programmes 
            ON intakes.programmes_programme_id = programmes.programme_id
        LEFT JOIN student_has_address
            ON students.student_id = student_has_address.students_student_id
    ");

        $students = $students->fetch_all(MYSQLI_ASSOC);

?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Attendace - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="https://unpkg.com/html5-qrcode"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body class="overflow-hidden" onload="initializeAttendnace()">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("Attendance");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("Attendance");
                                ?>
                            </div>
                            <div class="container p-4 m-0" id="skeleton-list">
                                <?php
                                include "./includes/components/skeleton-header.php";
                                include "./includes/components/skeleton-courses.php"; ?>
                            </div>
                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">

                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col-12 col-md-8">
                                                <div class="row p-0 m-0 g-0">
                                                    <span class="main-topic lh-lg">Attendance</span>
                                                    <span class="text-14 sub-text lh-base">Track Your Attendance Records and Class Participation</span>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4 mt-3 mt-xxl-0">
                                                <div class="row gx-3 justify-content-end">
                                                    <?php
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                    ?>
                                                        <div class="col-6 mt-3 mt-lg-0 m-md-0 text-end">
                                                            <button class="bg-transparent border-0 align-middle" onclick="window.location.href='attendance-permissions'">
                                                                <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme p-0 m-0"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    <?php
                                                    }
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('attendance', 25)) {
                                                    ?>
                                                        <div class="col-6 mt-3 mt-lg-0 m-md-0 text-end">
                                                            <button class="custom-btn w-100" onclick="openScannerModal()">Scan QR</button>
                                                        </div>
                                                    <?php
                                                    }

                                                    ?>

                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 g-0 align-content-center">
                                            <div class="col-12 mb-4">
                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                    <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">

                                                            <span class="text-14 dark-theme fw-medium"><?= $results->num_rows ?> Records</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-8 col-md-6 col-lg-7 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleAttendnacePagination('filter')">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsFilter" aria-expanded="false" aria-controls="studentsFilter">
                                                            <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                        </button>
                                                    </div>
                                                    <div class="col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100 d-flex align-items-center justify-content-center" type="button" data-bs-target="#downloadReport" data-bs-toggle="modal">
                                                            <span class="text-14 fw-semibold">Export Report</span>
                                                            <iconify-icon icon="line-md:download-loop" width="26" height="26" class="text-white px-3 pe-0"></iconify-icon>
                                                        </button>
                                                    </div>
                                                    <div class="collapse" id="studentsFilter">
                                                        <div class="card card-body border-0 p-0">
                                                            <div class="col-12">
                                                                <div class="row justify-content-end">

                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="faculty-selector" class="form-select custom-select mt-2" onchange="loadProgrammes(),handleAttendnacePagination('filter');">
                                                                            <option value="0" selected>- Select Faculty -</option>
                                                                            <?php
                                                                            $faculties = Database::search("SELECT * FROM `faculties`");
                                                                            while ($data = $faculties->fetch_assoc()) {
                                                                            ?>
                                                                                <option value="<?php echo $data["faculty_id"]; ?>"><?php echo $data["faculty"]; ?></option>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                        <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes(),handleAttendnacePagination('filter');">
                                                                            <option value="0" selected>- Select Programme -</option>
                                                                        </select>
                                                                        <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                        <select name="" id="intake-selector" disabled class="form-select custom-select mt-2" onchange="handleAttendnacePagination('filter')">
                                                                            <option value="0" selected>- Select Intake -</option>
                                                                        </select>
                                                                        <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                        <select class="form-select custom-select" id="month-selector" onchange="handleAttendnacePagination('filter')">
                                                                            <option value="0">- Select Month -</option>
                                                                            <?php
                                                                            $currentYear = date("Y");
                                                                            $currentMonth = date("m");

                                                                            // Loop from January to current month
                                                                            for ($m = 1; $m <= $currentMonth; $m++) {

                                                                                $month = str_pad($m, 2, "0", STR_PAD_LEFT);
                                                                                $monthName = date("F", strtotime("$currentYear-$month-01"));

                                                                                $value = $currentYear . "-" . $month;

                                                                                echo "<option value='$value'>$monthName $currentYear</option>";
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                        <input type="date" class="form-control custom-input" id="date" onchange="handleAttendnacePagination('filter')">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 d-none d-lg-block"></div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleAttendnacePagination('filter')">
                                                            <option selected value="10">10</option>
                                                            <option value="25">25</option>
                                                            <option value="50">50</option>
                                                            <option value="100">100</option>
                                                            <option value="250">250</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="table-responsive-wrapper">
                                                <div class="table-responsive" id="students-table-container">
                                                    <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                        <thead>
                                                            <tr class="br-t-5">
                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                    <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleAttendnacePagination('sortByName')">
                                                                            <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </div>
                                                                </th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Student ID</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleAttendnacePagination('sortByDate')">
                                                                    <div class="d-flex justify-content-between align-items-center w-100">
                                                                        <span class="text-14 main-text fw-medium">Date Time</span>
                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                    </div>
                                                                </th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Updated By</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="user-table-data">
                                                            <?php

                                                            $pageno = 1;
                                                            $results_count = $results->num_rows;
                                                            $result_per_page = 10;  // Consistent variable name

                                                            if ($results_count > $result_per_page) {
                                                                $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                            } else {
                                                                $number_of_pages = 1;
                                                            }

                                                            $page_results = ($pageno - 1) * $result_per_page;
                                                            $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                            if ($users->num_rows > 0) {
                                                                while ($data = $users->fetch_assoc()) {
                                                            ?>
                                                                    <tr>
                                                                        <td class="px-4 py-3 pe-0 align-middle">
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

                <div id="studentModal" class="modal fade">
                    <div class="modal-dialog modal-dialog-centered">

                        <div class="modal-content student-modal-card">
                            <button type="button"
                                class="btn-close custom-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>

                            <div id="student-loader" class="student-loader">
                                <div class="spinner-border text-warning"></div>
                                <p id="loader-text" class="loader-text">Processing scan...</p>
                            </div>

                            <div id="student-content" class="student-content d-none">
                                <div class="card-header-design"></div>
                                <div class="text-center">
                                    <img id="student-image" class="student-avatar">
                                </div>
                                <h5 id="student-name" class="text-center mt-2 fw-semibold"></h5>
                                <div class="student-details px-3 mt-3">
                                    <div class="detail-row">
                                        <div class="label">Registration No :</div>
                                        <div class="value" id="reg-no"></div>
                                    </div>

                                    <div class="detail-row">
                                        <div class="label">Course Name :</div>
                                        <div class="value" id="course-name"></div>
                                    </div>

                                    <div class="detail-row">
                                        <div class="label">NIC / Passport No :</div>
                                        <div class="value" id="nic"></div>
                                    </div>

                                </div>

                                <div class="text-center my-3">
                                    <div id="mark-status" class="status-badge">
                                        <iconify-icon icon="mdi:clock-outline"></iconify-icon>
                                        Waiting...
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>

                <div class="modal fade" id="qrScannerModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark border-0 rounded-4 overflow-hidden">

                            <!-- Header -->
                            <div class="modal-header border-0 text-white">
                                <h6 class="modal-title">Scan QR Code</h6>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>

                            <!-- FULL SCANNER AREA -->
                            <div class="modal-body p-0 position-relative">

                                <div id="qr-reader" class="scanner-full"></div>

                                <!-- Overlay Text -->
                                <div class="scanner-overlay-text">
                                    Align QR code inside the frame
                                </div>

                            </div>

                        </div>
                    </div>
                </div>

                <div class="modal fade br-0" id="downloadReport" aria-hidden="true" aria-labelledby="downloadReportLabel" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                        <div class="modal-content p-4">
                            <div class="modal-header p-0 border-0">
                                <h1 class="modal-title text-16 text-dark-color" id="editfacultyLabel" data-i18n="">Genarate Attendance Report</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0 mt-3">
                                <div class="col-12 p-0">
                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                        <div class="col-12 col-md-6 d-flex flex-column">
                                            <label for="faculty-selector" class="text-14 main-text required">Select Faculty</label>
                                            <select name="" id="report-faculty-selector" class="form-select custom-select mt-2" onchange="loadProgrammesForReport();">
                                                <option value="0" selected>- Select Faculty -</option>
                                                <?php
                                                $faculties = Database::search("SELECT * FROM `faculties`");
                                                while ($data = $faculties->fetch_assoc()) {
                                                ?>
                                                    <option value="<?php echo $data["faculty_id"]; ?>"><?php echo $data["faculty"]; ?></option>
                                                <?php
                                                }
                                                ?>
                                            </select>
                                            <span id="report-faculty-feedback" class="text-dark-red text-12"></span>
                                        </div>
                                        <div class="col-12 col-md-6 d-flex flex-column">
                                            <label for="report-programme-selector" class="text-14 main-text required">Select Programme</label>
                                            <select name="" id="report-programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakesForReport();">
                                                <option value="0" selected>- Select Programme -</option>
                                            </select>
                                            <span id="report-programme-feedback" class="text-dark-red text-12"></span>
                                        </div>
                                        <div class="col-12 col-md-6 d-flex flex-column">
                                            <label for="report-intake-selector" class="text-14 main-text required">Select Intake</label>
                                            <select name="" id="report-intake-selector" disabled class="form-select custom-select mt-2">
                                                <option value="0" selected>- Select Intake -</option>
                                            </select>
                                            <span id="report-intake-feedback" class="text-dark-red text-12"></span>
                                        </div>
                                        <div class="col-12 col-md-6 d-flex flex-column justify-content-end">
                                            <label for="report-month-selector" class="text-14 main-text required">Select Month</label>
                                            <select class="form-select custom-select mt-2" id="month-selector">
                                                <?php
                                                $currentYear = date("Y");
                                                $currentMonth = date("m");

                                                // Loop from January to current month
                                                for ($m = 1; $m <= $currentMonth; $m++) {

                                                    $month = str_pad($m, 2, "0", STR_PAD_LEFT);
                                                    $monthName = date("F", strtotime("$currentYear-$month-01"));

                                                    $value = $currentYear . "-" . $month;

                                                    $selected = ($month == $currentMonth) ? "selected" : "";

                                                    echo "<option value='$value' $selected>$monthName $currentYear</option>";
                                                }
                                                ?>
                                            </select>
                                            <span id="report-month-feedback" class="text-dark-red text-12"></span>
                                        </div>

                                    </div>
                                    <div class="row justify-content-end mt-4">
                                        <div class="col-12 col-lg-6 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                            <button class="w-100 custom-btn m-0" onclick="downloadCheckInCheckOutReport(this)">Genarate Report</button>
                                        </div>
                                    </div>
                                    <span class="text-12 text-dark-red" id="af-cover-feedback"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script id="c6z7wq">
                const studentsData = <?php echo json_encode($students); ?>;
            </script>
            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

        </body>

        </html>
<?php
    }
}
?>