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
        if ($sessionUserRole != 'Lecturer') {
            header("Location: ./dashboard");
        } else {
            $sessionUserObject = SessionUser::getCurrentUserObject();
            $id = $sessionUserObject['user_id'];
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            $timezone = new DateTimeZone("Asia/Colombo");
            $dateTime = new DateTime("now", $timezone);
            $todayDate = $dateTime->format('Y-m-d');
            $nowTime = $dateTime->format('H:i:s');
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>My Earnings History - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/calender.css">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden">
                <ul class="notifications"></ul>
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
                                    include "./includes/components/skeleton-table.php"; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0" id="accordionParent">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 col-md-8 col-lg-10">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">My Earnings History</span>
                                                        <span class="text-14 sub-text lh-base mt-1">Manage payment records, fees, and transaction history efficiently.</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-4 col-lg-2 mt-3 mt-lg-0" id="payoutBtnContainer">

                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0">

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
                                                                                    <span class="text-14 main-text fw-medium mx-2">Subject</span>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Type</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Count</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Rate</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php

                                                                        $withdrawalData = [];

                                                                        $assignmentsPayments = Database::search("
                                                                SELECT 
                                                                    s.subject_id,
                                                                    s.subject,
                                                                    s.subject_cover,
                                                                    l.per_assignment_fee,
                                                                    SUM(ap.amount) AS total_assignment_amount,
                                                                    
                                                                    l.lecturers_id
                                                                FROM 
                                                                    assignment_payments ap
                                                                INNER JOIN 
                                                                    lecturers l ON ap.lecturers_lecturers_id = l.lecturers_id
                                                                INNER JOIN 
                                                                    subjects s ON l.subjects_subject_id = s.subject_id
                                                                INNER JOIN 
                                                                    users u ON l.users_user_id = u.user_id
                                                                    WHERE 
                                                                    u.user_id = '$id'
                                                                    GROUP BY 
                                                                    s.subject_id, s.subject
                                                            ");

                                                                        if ($assignmentsPayments->num_rows > 0) {
                                                                            while ($assignmentsPayment = $assignmentsPayments->fetch_assoc()) {
                                                                                $assignmentCount = ceil($assignmentsPayment['total_assignment_amount'] / $assignmentsPayment['per_assignment_fee']);
                                                                                $withdrawalData[] = [
                                                                                    $assignmentsPayment['subject'],
                                                                                    $assignmentsPayment['subject_cover'],
                                                                                    'Assignment',
                                                                                    $assignmentCount,
                                                                                    $assignmentsPayment['per_assignment_fee'],
                                                                                    $assignmentsPayment['total_assignment_amount'],
                                                                                    2
                                                                                ];
                                                                            }
                                                                        }

                                                                        $lecturesPayments = Database::search("
                                                                SELECT 
                                                                    s.subject_id,
                                                                    s.subject,
                                                                    s.subject_cover,
                                                                    s.max_course_hours,
                                                                    l.hourly_rate,
                                                                    SUM(lp.amount) AS total_lectures_amount,
                                                                 l.lecturers_id
                                                                FROM 
                                                                    lecturer_payments lp
                                                                INNER JOIN 
                                                                    lecturers l ON lp.lecturers_lecturers_id = l.lecturers_id
                                                                INNER JOIN 
                                                                    subjects s ON l.subjects_subject_id = s.subject_id
                                                                INNER JOIN 
                                                                    users u ON l.users_user_id = u.user_id
                                                                                                                                               
                                                                WHERE 
                                                                    u.user_id = '$id'
                                                                   
                                                                GROUP BY 
                                                                    s.subject_id, s.subject
                                                            ");

                                                                        if ($lecturesPayments->num_rows > 0) {
                                                                            while ($lecturesPayment = $lecturesPayments->fetch_assoc()) {


                                                                                $completeHours = ceil($lecturesPayment['total_lectures_amount'] / $lecturesPayment['hourly_rate']);

                                                                                if ($completeHours == $lecturesPayment['max_course_hours'] || $completeHours > $lecturesPayment['max_course_hours']) {

                                                                                    $hours = $lecturesPayment['total_lectures_amount'] / $lecturesPayment['hourly_rate'];

                                                                                    $fullHours = floor($hours);
                                                                                    $minutes = round(($hours - $fullHours) * 60);

                                                                                    if ($minutes > 0) {
                                                                                        $formattedHours = "{$fullHours}h {$minutes}m";
                                                                                    } else {
                                                                                        $formattedHours = "{$fullHours}h";
                                                                                    }
                                                                                    $withdrawalData[] = [
                                                                                        $lecturesPayment['subject'],
                                                                                        $lecturesPayment['subject_cover'],
                                                                                        'Lectures',
                                                                                        $formattedHours,
                                                                                        $lecturesPayment['hourly_rate'],
                                                                                        $lecturesPayment['total_lectures_amount'],
                                                                                        1
                                                                                    ];
                                                                                }
                                                                            }
                                                                        }

                                                                        if (count($withdrawalData) > 0) {
                                                                            foreach ($withdrawalData as $key => $value) {
                                                                                $subject = $value[0];
                                                                                $subjectCover = $value[1];
                                                                                $subjectType = $value[2];
                                                                                $subjectHours = $value[3];
                                                                                $subjectRate = $value[4];
                                                                                $subjectAmount = $value[5];
                                                                                $type = $value[6];

                                                                        ?>
                                                                                <tr>
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['user_id']; ?>)">
                                                                                                <?php
                                                                                                if (!empty($subjectCover) && file_exists('./assets/imgs/subjects_covers/' . $subjectCover)) {
                                                                                                ?>
                                                                                                    <img src="./assets/imgs/subjects_covers/<?= $subjectCover; ?>" class="w-80 h-auto" alt="Subject image">
                                                                                                <?php
                                                                                                } else {
                                                                                                ?>
                                                                                                    <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-80 h-auto" alt="Subject image">
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                                <span class="text-14 main-text mx-2"><?= $subject; ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $subjectType; ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $subjectHours ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $subjectRate; ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $subjectAmount; ?></span></td>

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


                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/fileChooser.js"></script>

            </body>

            </html>
<?php
        }
    }
} ?>