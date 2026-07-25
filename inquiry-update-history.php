<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
require_once "./includes/SlipVerifier.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['inquiry-id']) && !empty($_GET['inquiry-id']))) {
            header("Location: ./students");
        } else {
            $leadId = (int) $_GET["inquiry-id"];

            $lead = Database::search("
    SELECT 
                                                             `students`.`student_id` AS 'sId', 
                                                         
                                                             `login_details`.`first_name`, 
                                                             `login_details`.`last_name`, 
                                                             `login_details`.`email`, 
                                                         
                                                             `students`.`mobile`, 
                                                         
                                                             `login_details`.`whatsapp`, 
                                                             `students`.`profile_img`, 
                                                             `login_details`.`date_registered`, 
                                                         
                                                             `title`.`title`, 
                                                         
                                                             `lead_student_status`.`lead_student_status`,
                                                             `lead_student_status`.`lead_student_status_id`,
                                                         
                                                             `login_details`.`verification`,
                                                             `login_details`.`reset_time`                                                         
                                                                                                                   
                                                         FROM `students`
                                                         
                                                         INNER JOIN `login_details`
                                                             ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                         
                                                         INNER JOIN `title`
                                                             ON `students`.`title_title_id` = `title`.`title_id`
                                                         
                                                         INNER JOIN `lead_student_status`
                                                             ON `students`.`lead_student_status_lead_student_status_id` = `lead_student_status`.`lead_student_status_id`
                                                         
                                                                                                                                                                        
                                                       
                                                         WHERE `students`.`student_id` = '" . $leadId . "'
                                                            GROUP BY `students`.`student_id`
                                                         ORDER BY `students`.`student_id` DESC
");

            if ($lead->num_rows == 0) {
                header("Location: ./registrations");
                exit;
            }

            $lead = $lead->fetch_assoc();
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Inquiries Update History - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                            include "includes/side-menu.php";
                            load_side_menu::load("Inquiries");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Inquiries");
                                    ?>
                                </div>

                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-table.php"; ?>
                                </div>

                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-12 col-xxl-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="">Inquiry Update History</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./registrations">Inquiries</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="#">Inquiry Update History</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                        <div class="col-12 p-0 m-0">
                                            <div class="row p-4 m-0 g-0 component-theme w-100">
                                                <div class="col-12">
                                                    <div class="payment-timeline">
                                                        <div class="timeline-progress">
                                                            <?php
                                                            $query = "SELECT * FROM `lead_student_status`";
                                                            $progress = 0;
                                                            $progressColor = "";
                                                            switch ($lead['lead_student_status_id'] == null ? 0 : (int)$lead['lead_student_status_id']) {

                                                                // Registration Fee Approval Pending
                                                                case 1:
                                                                    $progressColor = "pulse";
                                                                    $progress = 100 / 17 * 1;
                                                                    $query .= " WHERE `lead_student_status_id` IN (1,2,3)";
                                                                    break;

                                                                // Registration Fee Rejected
                                                                case 2:
                                                                    $progressColor = "reject";
                                                                    $progress = 100 / 17 * 1;
                                                                    $query .= " WHERE `lead_student_status_id` IN (1,2,3)";
                                                                    break;

                                                                // Registration Fee Approved
                                                                case 3:
                                                                    $progressColor = "completed";
                                                                    $progress = 100 / 17 * 2;
                                                                    $query .= " WHERE `lead_student_status_id` IN (1,2,3,4)";
                                                                    break;

                                                                // Payment Plan Created
                                                                case 4:
                                                                    $progressColor = "completed";
                                                                    $progress = 100 / 17 * 3;
                                                                    $query .= " WHERE `lead_student_status_id` IN (3,4,5)";
                                                                    break;

                                                                // Pending Enrollment
                                                                case 5:
                                                                    $progressColor = "pulse";
                                                                    $progress = 100 / 17 * 4;
                                                                    $query .= " WHERE `lead_student_status_id` IN (4,5,6,7)";
                                                                    break;

                                                                // Additional Documents Pending
                                                                case 6:
                                                                    $progressColor = "pulse";
                                                                    $progress = 100 / 17 * 5;
                                                                    $query .= " WHERE `lead_student_status_id` IN (5,6,7)";
                                                                    break;

                                                                // First Installment Payment Pending
                                                                case 7:
                                                                    $progressColor = "pulse";
                                                                    $progress = 100 / 17 * 6;
                                                                    $query .= " WHERE `lead_student_status_id` IN (6,7,8,9,10)";
                                                                    break;

                                                                // First Installment Approval Pending
                                                                case 8:
                                                                    $progressColor = "pulse";
                                                                    $progress = 100 / 17 * 7;
                                                                    $query .= " WHERE `lead_student_status_id` IN (7,8,9,10)";
                                                                    break;

                                                                // First Installment Rejected
                                                                case 9:
                                                                    $progressColor = "reject";
                                                                    $progress = 100 / 17 * 7;
                                                                    $query .= " WHERE `lead_student_status_id` IN (7,8,9,10)";
                                                                    break;

                                                                // First Installment Approved
                                                                case 10:
                                                                    $progressColor = "completed";
                                                                    $progress = 100 / 17 * 8;
                                                                    $query .= " WHERE `lead_student_status_id` IN (10,11)";
                                                                    break;

                                                                // Academic Document Review Pending
                                                                case 11:
                                                                    $progressColor = "pulse";
                                                                    $progress = 100 / 17 * 9;
                                                                    $query .= " WHERE `lead_student_status_id` IN (11,12,13)";
                                                                    break;

                                                                // Academic Documents Rejected
                                                                case 12:
                                                                    $progressColor = "reject";
                                                                    $progress = 100 / 17 * 9;
                                                                    $query .= " WHERE `lead_student_status_id` IN (11,12,13)";
                                                                    break;

                                                                // Academic Documents Approved
                                                                case 13:
                                                                    $progressColor = "completed";
                                                                    $progress = 100 / 17 * 10;
                                                                    $query .= " WHERE `lead_student_status_id` IN (13,14)";
                                                                    break;

                                                                // Ready for Enrollment
                                                                case 14:
                                                                    $progressColor = "completed";
                                                                    $progress = 100 / 17 * 11;
                                                                    $query .= " WHERE `lead_student_status_id` IN (3,4,5,10,13,14,17)";
                                                                    break;

                                                                // Program Assigned
                                                                case 15:
                                                                    $progressColor = "completed";
                                                                    $progress = 100 / 17 * 12;
                                                                    $query .= " WHERE `lead_student_status_id` IN (15,16)";
                                                                    break;

                                                                // Timetable Assigned
                                                                case 16:
                                                                    $progressColor = "completed";
                                                                    $progress = 100 / 17 * 13;
                                                                    $query .= " WHERE `lead_student_status_id` IN (16,17)";
                                                                    break;

                                                                // Enrollment Completed
                                                                case 17:
                                                                    $progressColor = "accepted";
                                                                    $progress = 100;
                                                                    $query .= " WHERE `lead_student_status_id` IN (17)";
                                                                    break;

                                                                default:
                                                                    $progressColor = "pulse";
                                                                    $progress = 0;
                                                                    $query .= " WHERE `lead_student_status_id` IN (1)";
                                                                    break;
                                                            }
                                                            ?>
                                                            <div class="progress-bar <?= $progressColor ?>" style="width: <?= $progress ?>%"></div>
                                                        </div>
                                                        <div class="timeline-items">
                                                            <?php
                                                            $icons = [

                                                                // 1 Registration Fee Approval Pending
                                                                "solar:clock-circle-bold",

                                                                // 2 Registration Fee Rejected
                                                                "solar:close-circle-bold",

                                                                // 3 Registration Fee Approved
                                                                "solar:check-circle-bold",

                                                                // 4 Payment Plan Created
                                                                "solar:document-add-bold",

                                                                // 5 Pending Enrollment
                                                                "solar:user-plus-bold",

                                                                // 6 Additional Documents Pending
                                                                "solar:documents-bold",

                                                                // 7 First Installment Payment Pending
                                                                "solar:wallet-money-bold",

                                                                // 8 First Installment Approval Pending
                                                                "solar:shield-check-bold",

                                                                // 9 First Installment Rejected
                                                                "solar:danger-circle-bold",

                                                                // 10 First Installment Approved
                                                                "solar:verified-check-bold",

                                                                // 11 Academic Document Review Pending
                                                                "solar:clipboard-text-bold",

                                                                // 12 Academic Documents Rejected
                                                                "solar:close-square-bold",

                                                                // 13 Academic Documents Approved
                                                                "solar:diploma-verified-bold",

                                                                // 14 Ready for Enrollment
                                                                "solar:user-check-bold",

                                                                // 15 Program Assigned
                                                                "solar:book-bookmark-bold",

                                                                // 16 Timetable Assigned
                                                                "solar:calendar-mark-bold",

                                                                // 17 Enrollment Completed
                                                                "solar:medal-ribbons-star-bold"
                                                            ];

                                                            $paymentStatus = Database::search($query);
                                                            while ($status = $paymentStatus->fetch_assoc()) {

                                                                $timelineItemColor = "";
                                                                $bubbleAction = "";

                                                                $rejectedStatuses = [2, 9, 12];

                                                                if ($lead['lead_student_status_id'] >= $status['lead_student_status_id']) {
                                                                    if ($lead['lead_student_status_id'] == 2 || $lead['lead_student_status_id'] == 9 || $lead['lead_student_status_id'] == 12) {
                                                                        $timelineItemColor = "reject";
                                                                    } else if ($lead['lead_student_status_id'] == 17) {
                                                                        $timelineItemColor = "accepted";
                                                                    } else {
                                                                        if (($lead['lead_student_status_id']  ==  $status['lead_student_status_id']) || ($lead['lead_student_status_id'] == null)) {
                                                                            $bubbleAction = "pulse";
                                                                        } else {
                                                                            $timelineItemColor = "completed";
                                                                        }
                                                                    }
                                                                }

                                                            ?>
                                                                <div class="timeline-item <?= $timelineItemColor ?>">
                                                                    <div class="timeline-bubble <?= $bubbleAction ?>">
                                                                        <iconify-icon icon="<?= $icons[$status['lead_student_status_id'] - 1] ?>" width="25" height="25"></iconify-icon>
                                                                    </div>
                                                                    <div class="timeline-label">
                                                                        <span><?= $status['lead_student_status'] ?></span>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Slip Line -->
                                                <div class="col-12 mt-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <?php
                                                                        $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `students_logs` 
                                                                                    WHERE `students_student_id`='" . $lead['sId'] . "'
                                                                                    ");
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
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Updated Status</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Log</th>
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody id="user-table-data">
                                                                                <?php
                                                                                $query = "SELECT * FROM `students_logs`
                                                                                    INNER JOIN `users` ON `students_logs`.`users_user_id` = `users`.`user_id`
                                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                                    LEFT JOIN `lead_student_status` ON `students_logs`.`lead_student_status_lead_student_status_id` = `lead_student_status`.`lead_student_status_id`
                                                                                    WHERE `students_student_id`='" . $lead['sId'] . "' ORDER BY `date_updated` DESC
                                                                                    ";

                                                                                $users = Database::search($query);
                                                                                if ($users->num_rows > 0) {
                                                                                    while ($data = $users->fetch_assoc()) {
                                                                                        $sbutton_class = "label-role-" . $data["lead_student_status_id"];
                                                                                ?>
                                                                                        <tr>
                                                                                            <td class="px-4 py-3 pe-0 align-middle">
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
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["date_updated"]; ?></span></td>
                                                                                            <td class="py-2 align-middle">
                                                                                                <label class="table-btn text-center label-student-status-<?= $data["lead_student_status_id"] ?> w-100"> <?php echo $data["lead_student_status"]; ?></label>
                                                                                            </td>
                                                                                            <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["log"]; ?></span></td>
                                                                                            <td colspan="4" class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["note"]; ?></span></td>
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
?>