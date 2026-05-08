<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['inquiry-id']) && !empty($_GET['inquiry-id']))) {
        header("Location: ./course-overview");
    } else {
        $id = $_GET['inquiry-id'];

        $query = "
                                    SELECT
                                        `students`.`profile_img`,
                                        `login_details`.`first_name`,
                                        `login_details`.`last_name`,
                                        `login_details`.`email`,
                                        `students`.`mobile`,
                                        `help_desk_category`.`help_desk_category`,
                                        `help_desk`.`title` AS `help_desk_title`,
                                        `help_desk_status`.`help_desk_status_id`,
                                        `help_desk_status`.`help_desk_status`,
                                        `title`.`title`,
                                        `students_has_help_desk`.`date_added`,
                                        `students_has_help_desk`.`students_has_help_desk_id`,
                                        `students_has_help_desk`.`message`,
                                        `students_has_help_desk`.`users_user_id`
                                    FROM `students_has_help_desk`
                                    INNER JOIN `help_desk` 
                                        ON `help_desk`.`help_desk` = `students_has_help_desk`.`help_desk_help_desk`
                                    INNER JOIN `help_desk_category` 
                                        ON `help_desk_category`.`help_desk_category_id` = `help_desk`.`help_desk_category_help_desk_category_id`
                                    INNER JOIN `help_desk_status` 
                                        ON `help_desk_status`.`help_desk_status_id` = `students_has_help_desk`.`help_desk_status_help_desk_status_id`
                                    INNER JOIN `students` 
                                        ON `students`.`student_id` = `students_has_help_desk`.`students_student_id`
                                    INNER JOIN `login_details`
                                        ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                                    INNER JOIN `title` 
                                        ON `title`.`title_id` = `students`.`title_title_id`
                                    WHERE `students_has_help_desk`.`students_has_help_desk_id` = '" . $id . "'
                                ";


        if ($sessionUserRole != "Developer") {
            $countQuery .= " AND `help_desk_category_help_desk_category_id` != '2'";
        }

        $isValdInquiry = Database::search($query);
        if ($isValdInquiry->num_rows == 0) {
            header("Location: ./my-inquiries");
        } else {
            $inquiryData = $isValdInquiry->fetch_assoc();

?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Student Inquiry - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden" onload="initializeStudentInquiry();">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("Help Desk");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Help Desk");
                                    ?>
                                </div>

                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-add-edit.php"; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 py-4 px-4 pe-4 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0 align-items-center">
                                                <div class="col-12 col-md-12 col-xl-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg"><span data-i18n="student-inquiries"></span> - <?= $inquiryData['title'] . ". " . $inquiryData['first_name'] . " " . $inquiryData['last_name'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="help-desk" href='help-desk'>Help Desk</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="student-inquiries">Student Inquiry</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-12 col-xxl-4 mt-3 mt-xxl-0">
                                                    <div class="row gx-3 justify-content-end">
                                                        <?php
                                                        if ($inquiryData['users_user_id'] == null) {
                                                        ?>
                                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn w-100" data-i18n="assign-user" type="button" onclick="openAssignUserModal();">
                                                                    Assign User
                                                                </button>
                                                            </div>
                                                        <?php
                                                        } else {
                                                        ?>
                                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn w-100" data-i18n="re-assign-user" type="button" onclick="openAssignUserModal();">
                                                                    Re-Assign User
                                                                </button>
                                                            </div>
                                                        <?php
                                                        }
                                                        if ($inquiryData['help_desk_status_id'] < 5) {
                                                        ?>
                                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn w-100" data-i18n="update-inquiry-status" type="button" onclick="openUpdateInquiryStatusModal();">
                                                                    Update Inquiry Status
                                                                </button>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 component-theme">
                                            <div class="payment-timeline">
                                                <div class="timeline-progress">
                                                    <?php
                                                    $query = "SELECT * FROM `help_desk_status`";
                                                    $progress = 0;
                                                    $progressColor = "";
                                                    switch ($inquiryData['help_desk_status_id']) {
                                                        case 1:
                                                            $progressColor = "completed";
                                                            $progress = 100 / 4 * 1;
                                                            break;
                                                        case 2:
                                                            $progressColor = "completed";
                                                            $progress = 100 / 4 * 2;
                                                            break;
                                                        case 3:
                                                            $progressColor = "completed";
                                                            $progress = 100 / 4 * 3;
                                                            break;
                                                        case 4:
                                                            $progressColor = "accepted";
                                                            $progress = 100 / 4 * 4;
                                                            break;
                                                    }

                                                    ?>
                                                    <div class="progress-bar <?= $progressColor ?>" style="width: <?= $progress ?>%"></div>
                                                </div>
                                                <div class="timeline-items">
                                                    <?php

                                                    $icons = ["majesticons:clock", "material-symbols:pending-outline", "clarity:administrator-line", "mdi:snowflake-approve", "fontisto:close"];

                                                    $inquiryStatus = Database::search($query);
                                                    while ($status = $inquiryStatus->fetch_assoc()) {
                                                        $timelineItemColor = "";
                                                        $bubbleAction = "";

                                                        if ($inquiryData['help_desk_status_id'] >= $status['help_desk_status_id']) {
                                                            if ($inquiryData['help_desk_status_id'] == 4) {
                                                                $timelineItemColor = "accepted";
                                                            } else {
                                                                $timelineItemColor = "completed";
                                                            }
                                                        }

                                                        if (($inquiryData['help_desk_status_id'] + 1)  ==  $status['help_desk_status_id']) {
                                                            $bubbleAction = "pulse";
                                                        }

                                                    ?>
                                                        <div class="timeline-item <?= $timelineItemColor ?>">
                                                            <div class="timeline-bubble <?= $bubbleAction ?>">
                                                                <iconify-icon icon="<?= $icons[$status['help_desk_status_id'] - 1] ?>" width="25" height="25"></iconify-icon>
                                                            </div>
                                                            <div class="timeline-label">
                                                                <span><?= $status['help_desk_status'] ?></span>
                                                                <div class="status-detail">Initial state</div>
                                                            </div>
                                                        </div>
                                                    <?php

                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>


                                        <div class="col-12 py-4 px-4 pe-4 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <span class="sub-topic">Inquiry Details</span>
                                                <div class="col-12 mt-3">
                                                    <div class="row g-4">
                                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                                            <label for="" class="required">Student Email</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="category" value="<?= $inquiryData['email'] ?>" disabled>
                                                            <span class="text-12 text-danger" id="category-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                                            <label for="" class="required">Student Mobile</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="title" value="<?= $inquiryData['mobile'] ?>" disabled>
                                                            <span class="text-12 text-danger" id="title-feedback"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 mt-3">
                                                    <div class="row g-4">
                                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                                            <label for="" class="required">Selected Category</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="category" value="<?= $inquiryData['help_desk_category'] ?>" disabled>
                                                            <span class="text-12 text-danger" id="category-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                                            <label for="" class="required">Selected Title</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="title" value="<?= $inquiryData['help_desk_title'] ?>" disabled>
                                                            <span class="text-12 text-danger" id="title-feedback"></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 mt-3">
                                                    <div class="row p-0 m-0 g-0">
                                                        <label for="" class="required">Message</label>
                                                        <textarea name="" id="help-desk-message" class="form-control custom-input mt-2 text-start" disabled>
                                                            <?= $inquiryData['message'] ?>
                                                        </textarea>
                                                        <span class="text-12 text-danger" id="message-feedback"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <?php
                                                                $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `help_desk_logs` WHERE `students_has_help_desk_students_has_help_desk_id`='" . $id . "'");
                                                                $data = $logs_count->fetch_assoc();
                                                                ?>
                                                                <span class="text-14 dark-theme fw-medium"><?= $data['count']; ?> Logs</span>
                                                            </div>
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
                                                                                    <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php
                                                                        $query = "SELECT * FROM `help_desk_logs`
                                                                    INNER JOIN `users` ON `help_desk_logs`.`users_user_id` = `users`.`user_id`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                    WHERE `students_has_help_desk_students_has_help_desk_id` = '" . $id . "' ORDER BY `added_at` DESC";

                                                                        $users = Database::search($query);
                                                                        if ($users->num_rows > 0) {
                                                                            while ($data = $users->fetch_assoc()) {
                                                                        ?>
                                                                                <tr>
                                                                                    <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["user_id"]; ?>" onchange="checkRowEvents(<?= $data['user_id']; ?>);">
                                                                                            <?php
                                                                                            if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
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
                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["added_at"]; ?></span></td>
                                                                                    <td colspan="4" class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["log"]; ?></span></td>
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

                <?php
                if ($inquiryData['help_desk_status_id'] < 5) {
                ?>
                    <div class="modal fade" id="updateInquiryStatus" tabindex="-1" role="dialog" aria-labelledby="updateInquiryStatusTitle" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLongTitle">Update Payment Status</h5>
                                </div>
                                <div class="modal-body">
                                    <div class="col-12 p-0 g-0">
                                        <div class="row gx-4 gy-3">
                                            <div class="col-12 d-flex flex-column">
                                                <label class="text-14 main-text required" for="">Payment Status</label>
                                                <select class="form-select custom-select mt-2" id="status-selector">
                                                    <?php
                                                    $status = Database::search("SELECT * FROM `help_desk_status` WHERE `help_desk_status_id` > '" . $inquiryData['help_desk_status_id'] . "'");
                                                    while ($data = $status->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?= $data["help_desk_status_id"]; ?>"><?= $data["help_desk_status"]; ?></option>
                                                    <?php
                                                    }
                                                    ?>
                                                </select>
                                                <span id="status-selector-feedback" class="text-dark-red text-12"></span>
                                            </div>
                                            <div class="col-12 d-flex flex-column">
                                                <label class="text-14 main-text required" for="">Note</label>
                                                <textarea class="form-control custom-input mt-2" id="note" rows="5"></textarea>
                                                <span id="note-feedback" class="text-dark-red text-12"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="col-12 col-md-4 custom-btn" onclick="updateInquiryStatus(<?= $id ?>);">Update</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                }
                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('subject_lecturers', 2, $id)) {
                ?>
                    <!-- Add lecturer modal => Start -->
                    <div class="modal fade br-0" id="assignUser" aria-hidden="true" aria-labelledby="assignUserLabel" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                            <div class="modal-content p-4">
                                <div class="modal-header p-0 border-0">
                                    <h1 class="modal-title text-16 text-dark-color" id="assignUserLabel">Assign User</h1>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-0 mt-3">
                                    <div class="col-12 p-0">
                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                            <div class="col-12 d-flex flex-column">
                                                <label for="" class="text-14 main-text">Select User</label>
                                                <div class="custom-select-container" id="userSelect">
                                                    <div class="custom-select-display">- Select User -</div>
                                                    <div class="custom-options">
                                                        <input type="text" placeholder="Search User...">
                                                        <ul>
                                                            <?php
                                                            $users = Database::search("SELECT `user_id`,`title`,`user_fname`,`user_lname`,`user_role`
                                                                    FROM `users`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                    INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                                                                    WHERE `user_roles_user_role_id` NOT IN (1,2,8) 
                                                                    AND `status_status_id`='1'");
                                                            while ($data = $users->fetch_assoc()) {
                                                            ?>
                                                                <li data-value="<?= $data['user_id'] ?>">
                                                                    <?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"] . " (" . $data['user_role'] . ")"; ?>
                                                                </li>
                                                            <?php } ?>
                                                        </ul>
                                                    </div>
                                                    <input type="hidden" name="user_id" id="userHidden" class="form-control custom-input">
                                                </div>
                                                <span class="text-12 text-dark-red" id="user-feedback"></span>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer p-0 mt-4 border-0">
                                    <button class="col-8 col-md-4 custom-btn m-0" onclick="assignUserToInquiry(<?= $id ?>);">Add User</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Add lecturer modal => End -->
                <?php
                }
                ?>

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