<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";

/* ---------------------------------------------------------------
 * Guards (early exit instead of deep nesting)
 * ------------------------------------------------------------- */
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
    exit;
}

$sessionUserRole = SessionUser::getCurrentUserRole();
if ($sessionUserRole == null) {
    header("Location: ./dashboard");
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject();

if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payment-id']) && !empty($_GET['payment-id']))) {
    header("Location: ./lecturers-assignment-payments");
    exit;
}

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

if (!(SessionUser::ifUserIsPro($sessionUserRole)
    || SessionUser::ifUserIsAcademic($sessionUserRole)
    || SessionUser::ifUserIsAccounts($sessionUserRole)
    || UserPermissions::validPermisisonType('payments'))) {
    header("Location: ./dashboard");
    exit;
}

$id = (int) $_GET['payment-id'];

/* ---------------------------------------------------------------
 * Payment + lecturer + status + scheduled-by user
 * ------------------------------------------------------------- */
$paymentResult = Database::search("SELECT
        `assignment_payments`.`assignment_payments_id`,
        `assignment_payments`.`amount`,
        `assignment_payments`.`note`,
        `assignment_payments`.`date_added`,
        `assignment_payments`.`assignments_assignment_id`,
        `assignment_payments`.`lecturers_paid_payments_lecturers_paid_payment_id`,
        `assignment_payments`.`lecturers_lecturers_id`,
        `earning_status`.`earning_status_id`,
        `earning_status`.`earning_status`,
        `lecturers`.`per_assignment_fee`,
        `title`.`title`,
        `users`.`user_fname`,
        `users`.`user_lname`,
        `users`.`profile_img`
    FROM `assignment_payments`
    INNER JOIN `earning_status` ON `earning_status`.`earning_status_id` = `assignment_payments`.`earning_status_earning_status_id`
    INNER JOIN `lecturers` ON `lecturers`.`lecturers_id` = `assignment_payments`.`lecturers_lecturers_id`
    INNER JOIN `users` ON `users`.`user_id` = `lecturers`.`users_user_id`
    INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
    WHERE `assignment_payments`.`assignment_payments_id` = '" . $id . "'");

if ($paymentResult->num_rows == 0) {
    header("Location: ./lecturers-assignment-payments");
    exit;
}

$paymentData = $paymentResult->fetch_assoc();

/* Scheduled by (users_user_id on assignment_payments) */
$scheduledBy = Database::search("SELECT `title`.`title`, `users`.`user_fname`, `users`.`user_lname`
    FROM `assignment_payments`
    INNER JOIN `users` ON `users`.`user_id` = `assignment_payments`.`users_user_id`
    INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id`
    WHERE `assignment_payments`.`assignment_payments_id` = '" . $id . "'");
$scheduledByName = 'N/A';
if ($scheduledBy->num_rows > 0) {
    $sb = $scheduledBy->fetch_assoc();
    $scheduledByName = $sb['title'] . "." . $sb['user_fname'] . " " . $sb['user_lname'];
}

/* Optional: single assignment attached to the payment */
$assignmentInfo = null;
if (!empty($paymentData['assignments_assignment_id'])) {
    $assignmentResult = Database::search("SELECT `assignment_code`,`assignment`,`subject`,`programme`,`intake`,`faculty` FROM `assignments`
        INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
        INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
        INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
        INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
        INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
        WHERE `assignment_id` = '" . (int) $paymentData['assignments_assignment_id'] . "'");
    if ($assignmentResult->num_rows > 0) {
        $assignmentInfo = $assignmentResult->fetch_assoc();
    }
}

/* ---------------------------------------------------------------
 * Marked assignments included in this payment
 * ------------------------------------------------------------- */
$markedQuery = "SELECT
        `lecturers_has_students_has_assignments`.`lecturers_has_students_has_assignments_id`,
        `lecturers_has_students_has_assignments`.`marks`,
        `students_has_assignments`.`uploaded_at`,
        `title`,`first_name`,`last_name`,`profile_img`,
        `students_has_intakes`.`student_id`,
        `assignments`.`assignment_code`,
        `assignments`.`assignment`,
        `assignment_payments_has_marked_assignments`.`date_added` AS marked_added
    FROM `assignment_payments_has_marked_assignments`
    INNER JOIN `lecturers_has_students_has_assignments` ON `lecturers_has_students_has_assignments`.`lecturers_has_students_has_assignments_id` = `assignment_payments_has_marked_assignments`.`lecturers_has_students_has_assignments_id`
    INNER JOIN `students_has_assignments` ON `students_has_assignments`.`students_has_assignments_id` = `lecturers_has_students_has_assignments`.`students_has_assignments_students_has_assignments_id`
    INNER JOIN `students` ON `students_has_assignments`.`students_student_id` = `students`.`student_id`
    INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
    INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
    INNER JOIN `assignments` ON `students_has_assignments`.`assignments_assignment_id` = `assignments`.`assignment_id`
    INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
    INNER JOIN `students_has_intakes` ON (`students_has_assignments`.`students_student_id` = `students_has_intakes`.`students_student_id`
        AND `students_has_intakes`.`intakes_intake_id` = `levels`.`intakes_intake_id`)
    WHERE `assignment_payments_has_marked_assignments`.`assignment_payments_id` = '" . $id . "'
    ORDER BY `assignment_payments_has_marked_assignments`.`date_added` DESC";

$markedAssignments = Database::search($markedQuery);
$markedCount = $markedAssignments->num_rows;

$statusId = (int) $paymentData['earning_status_id'];
$isPaid = !empty($paymentData['lecturers_paid_payments_lecturers_paid_payment_id']);

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Lecturer Assignment Payment - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                include "includes/side-menu.php";
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
                        include "./includes/components/skeleton-add-edit.php"; ?>
                    </div>

                    <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                        <div class="row p-4 m-0 g-0">

                            <!-- Page heading + breadcrumb -->
                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 col-lg-8">
                                        <div class="row">
                                            <span class="main-topic lh-lg">Assignment Payment Details</span>
                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                <ol class="breadcrumb m-0">
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./payments'>Payments</a></li>
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./lecturers-payemnts'>Lecturers Payments</a></li>
                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./lecturers-assignment-payments'>Lecturers Assignment Payments</a></li>
                                                </ol>
                                            </nav>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 p-4 component-theme">
                                <div class="row p-0 m-0 align-content-center">

                                    <!-- Status timeline -->
                                    <div class="col-12">
                                        <div class="payment-timeline">
                                            <div class="timeline-progress">
                                                <?php
                                                $statusQuery = "SELECT * FROM `earning_status`";
                                                $progress = 0;
                                                $progressColor = "";
                                                switch ($statusId) {
                                                    case 1:
                                                        $progressColor = "completed";
                                                        $progress = 100 / 2 * 1;
                                                        $statusQuery .= " WHERE `earning_status_id` IN (1,2)";
                                                        break;
                                                    case 2:
                                                        $progressColor = "accepted";
                                                        $progress = 100 / 2 * 2;
                                                        $statusQuery .= " WHERE `earning_status_id` IN (1,2)";
                                                        break;
                                                    case 3:
                                                        $progressColor = "reject";
                                                        $progress = 100 / 2 * 2;
                                                        $statusQuery .= " WHERE `earning_status_id` IN (1,3)";
                                                        break;
                                                    default:
                                                        $progressColor = "Pending";
                                                        $statusQuery .= " WHERE `earning_status_id` IN (1,2)";
                                                        break;
                                                }
                                                ?>
                                                <div class="progress-bar <?= $progressColor ?>" style="width: <?= $progress ?>%"></div>
                                            </div>
                                            <div class="timeline-items">
                                                <?php
                                                $icons = ["material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "mdi:approve"];

                                                $paymentStatus = Database::search($statusQuery);
                                                while ($status = $paymentStatus->fetch_assoc()) {
                                                    $timelineItemColor = "";
                                                    $bubbleAction = "";

                                                    if ($statusId >= $status['earning_status_id']) {
                                                        if ($statusId == 3) {
                                                            $timelineItemColor = "reject";
                                                        } else if ($statusId == 2) {
                                                            $timelineItemColor = "accepted";
                                                        } else {
                                                            if ($statusId == $status['earning_status_id']) {
                                                                $bubbleAction = "pulse";
                                                            } else {
                                                                $timelineItemColor = "completed";
                                                            }
                                                        }
                                                    }

                                                    $statusDetails = "Initial state";
                                                    if ($status['earning_status_id'] == 1) {
                                                        $statusDetails = "Pending scheduling";
                                                    } else if ($status['earning_status_id'] == 2) {
                                                        $statusDetails = "Confirmed lecturer assignment payment";
                                                    } else if ($status['earning_status_id'] == 3) {
                                                        $statusDetails = "Rejected lecturer assignment payment";
                                                    }
                                                ?>
                                                    <div class="timeline-item <?= $timelineItemColor ?>">
                                                        <div class="timeline-bubble <?= $bubbleAction ?>">
                                                            <iconify-icon icon="<?= $icons[$status['earning_status_id'] - 1] ?>" width="25" height="25"></iconify-icon>
                                                        </div>
                                                        <div class="timeline-label">
                                                            <span><?= e($status['earning_status']) ?></span>
                                                            <div class="status-detail"><?= $statusDetails ?></div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Payment card -->
                                    <div class="payment-card-container mt-4">
                                        <div class="payment-card">
                                            <div class="card-background"></div>
                                            <div class="card-content">
                                                <div class="payment-header">
                                                    <div class="reference-section">
                                                        <span class="reference-label">PAYMENT LECTURER</span>
                                                        <h2 class="reference-number"><?= e($paymentData["title"] . "." . $paymentData["user_fname"] . " " . $paymentData["user_lname"]); ?></h2>
                                                        <hr>
                                                    </div>
                                                </div>

                                                <span class="reference-label">Payment Summary</span>
                                                <div class="details-row d-flex flex-column flex-lg-row">
                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="material-symbols:assignment-outline-rounded" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Marked Assignments</span>
                                                            <span class="detail-value"><?= $markedCount ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="lsicon:amount-dollar-outline" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Fee Per Assignment</span>
                                                            <span class="detail-value">LKR <?= number_format($paymentData['per_assignment_fee'], 2) ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="lsicon:amount-dollar-outline" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Scheduled Amount</span>
                                                            <span class="detail-value">LKR <?= number_format($paymentData['amount'], 2) ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="solar:calendar-date-broken" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Scheduled Date Time</span>
                                                            <span class="detail-value"><?= date('Y-m-d h:i A', strtotime($paymentData["date_added"])) ?></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="details-row d-flex flex-column flex-lg-row">
                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="hugeicons:user" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Scheduled By</span>
                                                            <span class="detail-value"><?= e($scheduledByName) ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="mingcute:time-duration-line" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Payment Status</span>
                                                            <span class="detail-value"><?= e($paymentData['earning_status']) ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="lsicon:amount-dollar-outline" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Paid Reference</span>
                                                            <span class="detail-value"><?= $isPaid ? '#' . e($paymentData['lecturers_paid_payments_lecturers_paid_payment_id']) : 'Not paid yet' ?></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php if ($assignmentInfo !== null) { ?>
                                                    <span class="reference-label mt-4">Assignment</span>
                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                        <div class="detail-item">
                                                            <div class="detail-icon">
                                                                <iconify-icon icon="tdesign:menu" width="20" height="20"></iconify-icon>
                                                            </div>
                                                            <div class="detail-content">
                                                                <span class="detail-label"><?= e($assignmentInfo['assignment_code'] . " - " . $assignmentInfo['assignment']) ?></span>
                                                                <span class="detail-value"><?= e($assignmentInfo['faculty'] . " / " . $assignmentInfo['programme'] . " / " . $assignmentInfo['intake'] . " / " . $assignmentInfo['subject']) ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>

                                                <div class="details-row d-flex flex-column flex-lg-row mt-4">
                                                    <div class="detail-item">
                                                        <div class="detail-icon">
                                                            <iconify-icon icon="hugeicons:note" width="20" height="20" class="justify-content-center"></iconify-icon>
                                                        </div>
                                                        <div class="detail-content">
                                                            <span class="detail-label">Note</span>
                                                            <span class="detail-value"><?= trim((string) $paymentData["note"]) == '' ? 'N/A' : e($paymentData["note"]) ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Update scheduled payment (only while status = Scheduled and not yet paid) -->
                            <?php if ($statusId == 1 && !$isPaid) { ?>
                                <div class="col-12 mt-4">
                                    <div class="row p-4 m-0 g-0 component-theme">
                                        <span class="sub-topic">Update Scheduled Assignment Payment</span>
                                        <div class="col-12 p-0 g-0 mt-4">
                                            <div class="row gx-4 gy-3 align-items-end">
                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                    <label class="text-14 main-text" for="marked-count">Marked Assignments</label>
                                                    <input type="number" class="form-control custom-input mt-2" id="marked-count" disabled value="<?= $markedCount ?>">
                                                </div>
                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                    <label class="text-14 main-text" for="fee">Fee Per Assignment</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="fee" disabled value="<?= number_format($paymentData['per_assignment_fee'], 2) ?>">
                                                </div>
                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="amount">Amount (Schedule to pay)</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="amount" value="<?= e($paymentData['amount']) ?>">
                                                    <span id="amount-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column">
                                                    <label class="text-14 main-text" for="note">Note</label>
                                                    <textarea class="form-control custom-input mt-2" rows="5" id="note"><?= e($paymentData['note']) ?></textarea>
                                                    <span id="note-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                            </div>
                                            <div class="row justify-content-end mt-4">
                                                <div class="col-12 col-lg-4">
                                                    <button class="custom-btn w-100" onclick="updateScheduledLecturerAssignmentPayment(this,<?= $id ?>);">Save Changes</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>

                            <!-- Marked assignments included in this payment -->
                            <div class="col-12 mt-4">
                                <div class="row p-4 m-0 g-0 component-theme">
                                    <div class="col-12 mb-4">
                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                            <div class="col-12 col-md-4 col-lg-3 d-flex flex-column">
                                                <div class="d-flex custom-div align-items-center justify-content-center">
                                                    <span class="text-14 dark-theme fw-medium"><?= $markedCount ?> Marked Assignments</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="row p-0 m-0 g-0 align-content-center">
                                            <div class="overflow-x-auto p-0 m-0 g-0" id="marked-assignments-table-container">
                                                <table class="table bl-solid m-0 p-0 g-0" id="marked-assignments-table">
                                                    <thead>
                                                        <tr class="br-t-5">
                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Student</th>
                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>
                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Assignment</th>
                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Upload Date & Time</th>
                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Marks</th>
                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Added To Payment</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        if ($markedCount > 0) {
                                                            while ($data = $markedAssignments->fetch_assoc()) {
                                                        ?>
                                                                <tr>
                                                                    <td class="px-4 py-3 align-middle">
                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                            <?php
                                                                            if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                            ?>
                                                                                <img src="./assets/imgs/students/<?= e($data["profile_img"]); ?>" class="hwr-40" alt="Student image">
                                                                            <?php
                                                                            } else {
                                                                                $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                            ?>
                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= e($firstNameInitial . $lastNameInitial); ?></div>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                            <span class="text-14 main-text mx-2"><?= e($data["title"] . "." . $data["first_name"] . " " . $data["last_name"]); ?></span>
                                                                        </div>
                                                                    </td>
                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= e($data["student_id"]); ?></span></td>
                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= e($data["assignment_code"] . " - " . $data["assignment"]); ?></span></td>
                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= e($data["uploaded_at"]); ?></span></td>
                                                                    <td class="px-4 py-2 align-middle">
                                                                        <?php if ($data['marks'] === null || $data['marks'] === '') { ?>
                                                                            <button class="table-btn table-btn-purple">Pending</button>
                                                                        <?php } else { ?>
                                                                            <span class="text-14 main-text"><?= e($data['marks']); ?></span>
                                                                        <?php } ?>
                                                                    </td>
                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= date("Y-m-d h:i A", strtotime($data["marked_added"])) ?></span></td>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/js/fileChooser.js"></script>
</body>

</html>