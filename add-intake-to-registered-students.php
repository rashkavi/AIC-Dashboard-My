<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
require_once "./includes/Limitation.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] != 'GET' && !isset($_GET['intake-id']) && empty($_GET['intake-id'])) {
            header("Location: ./intakes");
        } else {
            $id = $_GET['intake-id'];
            $intake = Database::search("SELECT * FROM `intakes` INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `intake_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                header("Location: ./intakes");
            } else {
                $intakeData = $intake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $id))) {
                    header("Location: ./intake-details?intake-id=" . $id);
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Add Intake To Registered Students - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden">
                        <div class="notifications"></div>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php
                                    include "./includes/side-menu.php";
                                    load_side_menu::load("Course Overview");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("Course Overview");
                                            ?>
                                        </div>
                                        <div class="container p-0 m-0" id="skeleton-list">
                                            <?php include "./includes/components/skeleton-table.php"; ?>
                                        </div>
                                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <div class="col-12 col-md-12 col-lg-8">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg" data-i18n="add-registered-student">Add Registered Students</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="faculties" href="./faculties">Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties?id=<?php echo $intakeData['faculties_faculty_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intake-details?intake-id=<?= $id ?>"><?= $intakeData["intake"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="add-registered-student">Add Registered Students</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php
                                                if (!Limitation::availableStudents()) {
                                                    echo Limitation::getLimitExceededMessage();
                                                } else {
                                                ?>
                                                    <div class="col-12 p-4 component-theme">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <div class="col-12">
                                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                    <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                                        <div class="d-flex custom-div align-items-center justify-content-center">
                                                                            <?php
                                                                            $studentCount = Database::search("SELECT COUNT(DISTINCT `student_id`) AS unique_student_count FROM `students` WHERE `student_id` NOT IN (SELECT `students_student_id` FROM `students_has_intakes` WHERE `intakes_intake_id`='" . $id . "')");
                                                                            $data = $studentCount->fetch_assoc();
                                                                            ?>
                                                                            <span class="text-14 dark-theme fw-medium"><?php echo $data["unique_student_count"]; ?> <span data-i18n="students"></span></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-8 col-md-8 col-lg-9 d-flex flex-column">
                                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleRegisteredStudentsPagination(<?= $id ?>,'filter')">
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                                        <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsFilter" aria-expanded="false" aria-controls="studentsFilter">
                                                                            <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                        </button>
                                                                    </div>
                                                                    <div class="collapse" id="studentsFilter">
                                                                        <div class="card card-body border-0 p-0">
                                                                            <div class="col-12">
                                                                                <div class="row justify-content-end">
                                                                                    <div class="col-12 col-md-2 d-flex flex-column justify-content-end">
                                                                                        <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleRegisteredStudentsPagination(<?= $id ?>,'filter')">
                                                                                            <option selected value="0">- Filter Data -</option>
                                                                                            <option value="1">A-Z</option>
                                                                                            <option value="2">Z-A</option>
                                                                                            <option value="3">Active</option>
                                                                                            <option value="4">Inactive</option>
                                                                                            <option value="5">Recently Registered</option>
                                                                                            <option value="6">Early Registered</option>
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                        <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleRegisteredStudentsPagination(<?= $id ?>,'filter')">
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
                                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Email Address</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Mobile</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Admission Date</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <span class="text-14 main-text fw-medium">Status</span>
                                                                                        <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>

                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="user-table-data">
                                                                            <?php
                                                                            $query = "SELECT 
                                                                   `s`.`student_id` AS `sId`,
                                                                   `ld`.`first_name`,
                                                                   `ld`.`last_name`,
                                                                   `ld`.`email`,
                                                                   `s`.`mobile`,
                                                                   `s`.`profile_img`,
                                                                   `ld`.`date_registered`,
                                                                   `t`.`title`,
                                                                   `st`.`status`
                                                               FROM `students` AS `s`
                                                               INNER JOIN `login_details` AS `ld`
                                                                   ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                                                               INNER JOIN `title` AS `t`
                                                                   ON `s`.`title_title_id` = `t`.`title_id`
                                                               INNER JOIN `status` AS `st`
                                                                   ON `ld`.`status_status_id` = `st`.`status_id`
                                                               LEFT JOIN `students_has_intakes` AS `shi`
                                                                   ON `s`.`student_id` = `shi`.`students_student_id`
                                                                   AND `shi`.`intakes_intake_id` = '" . $id . "'
                                                               WHERE `shi`.`students_student_id` IS NULL
                                                               ORDER BY `s`.`student_id` DESC";


                                                                            $results = Database::search($query);
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
                                                                                    $name = $data["title"] . "." . $data["first_name"] . " " . $data["last_name"];
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
                                                                                                    <span class="text-14 main-text mx-2"><?php echo $name; ?></span>
                                                                                                </div>
                                                                                            </div>
                                                                                        </td>
                                                                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["sId"]; ?></span></td>
                                                                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>
                                                                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["mobile"]; ?></span></td>
                                                                                        <td colspan="3" class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $data["date_registered"]; ?></span></td>

                                                                                        <td class="px-4 py-2 align-middle">
                                                                                            <?php
                                                                                            $status_classes = [
                                                                                                "Active"     => "table-btn-success",
                                                                                                "Inactive"  => "table-btn-danger",
                                                                                            ];
                                                                                            $sbutton_class = $status_classes[$data["status"]];
                                                                                            ?>
                                                                                            <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["status"]; ?></button>
                                                                                        </td>

                                                                                        <td class="px-4 pt-3 pb-3 align-middle">
                                                                                            <button class="table-btn bg-dark-theme text-white active w-100" onclick="openRegisteredStudentAddIntakeModal(<?= $data['sId'] ?>,<?= $id ?>, '<?= $name ?>')">Add</button>
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
                                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleRegisteredStudentsPagination(<?= $id ?>,'previousPage');">
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
                                                                                            $action = $isActive ? '' : "onclick=\"handleRegisteredStudentsPagination('{$id}','goToPage', '{$i}')\"";
                                                                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                                                        }

                                                                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                                                                renderPageButton($id, $i, $pageno == $i);
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
                                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleRegisteredStudentsPagination(<?= $id ?>,'nextPage',<?= $number_of_pages ?>);">
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
                                                <?php
                                                }
                                                ?>


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

                        <div class="modal fade" id="studentAddIntake" tabindex="-1" aria-labelledby="studentAddIntakeLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h1 class="modal-title fs-5" id="studentAddIntakeLabel">Add Student</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="col-12 mt-3 m-md-0 m-lg-0 p-0">
                                            <div class="row gx-4 gy-4">
                                                <div class="col-12 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="">Student Id</label>
                                                    <input type="text" class="form-control custom-input mt-2" readonly id="student-id">
                                                </div>
                                                <div class="col-12 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="">Student Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" readonly id="student-name">
                                                </div>
                                                <div class="col-12 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="">Student Intake Registration Number</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="student-intake-id" maxlength="45">
                                                    <span id="student-intake-id-feedback" class="text-dark-red text-12"></span>
                                                </div>

                                                <div class="col-12 d-flex flex-column <?= SessionUser::ifUserIsCounsiler($sessionUserRole) && !SessionUser::ifUserIsBranchManager($sessionUserRole) ? 'd-none' : '' ?>">
                                                    <label class="text-14 main-text required" for="">Assign Counselor</label>
                                                    <select name="" id="user" class="form-select custom-select mt-2">
                                                        <option value="0" disabled <?= SessionUser::ifUserIsCounsiler($sessionUserRole) && !SessionUser::ifUserIsBranchManager($sessionUserRole) ? '' : 'selected' ?>>- Select -</option>
                                                        <?php
                                                        $query = "SELECT * FROM `users`
                                                            INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id` 
                                                            WHERE `user_roles_user_role_id` IN (3,9) AND `status_status_id`='1'";

                                                        if (SessionUser::ifUserIsCounsiler($sessionUserRole) && !SessionUser::ifUserIsBranchManager($sessionUserRole)) {
                                                            $query .= " AND `user_id`='" . $sessionUserObject['user_id'] . "'";
                                                        }

                                                        $users = Database::search($query);

                                                        while ($user = $users->fetch_assoc()) {

                                                        ?>
                                                            <option value="<?php echo $user["user_id"]; ?>" <?= SessionUser::ifUserIsCounsiler($sessionUserRole) && !SessionUser::ifUserIsBranchManager($sessionUserRole) ? 'selected' : ''  ?>><?php echo $user["title"] . '.' . $user["user_fname"] . ' ' . $user["user_lname"]; ?></option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span id="user-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="custom-btn bg-secondary px-5" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="custom-btn px-5" id="add-registered-student-to-intake">Add Student</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/fileChooser.js"></script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>