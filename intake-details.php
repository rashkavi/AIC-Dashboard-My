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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['intake-id']) && !empty($_GET['intake-id']))) {
            header("Location: ./intakes");
        } else {
            $id = $_GET['intake-id'];
            $intake = Database::search("SELECT * FROM `intakes` INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `intake_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                header("Location: ./intakes");
            } else {
                $intakeData = $intake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $cardData = Database::search("WITH payment_totals_cte AS (
                    SELECT 
                        SUM(CASE WHEN `payment_status_payment_status_id` = 8 THEN amount ELSE 0 END) AS total_payments,
                        SUM(CASE WHEN `payment_status_payment_status_id` = 1 THEN amount ELSE 0 END) AS awaiting_payments,
                        SUM(CASE WHEN `payment_status_payment_status_id` IN (2,5) THEN amount ELSE 0 END) AS pending_approval,
                        SUM(CASE WHEN `payment_status_payment_status_id` IN (4,7) THEN amount ELSE 0 END) AS reject_payments
                    FROM course_payments 
                    WHERE `payment_plan_has_students_has_intakes_id` 
                    IN (SELECT `payment_plan_has_students_has_intakes_id` 
                        FROM `payment_plan_has_students_has_intakes` 
                        WHERE `students_has_intakes_students_has_intakes_id` 
                        IN (SELECT `students_has_intakes_id` 
                            FROM `students_has_intakes` 
                            WHERE `intakes_intake_id`='" . $id . "'))
                )
                SELECT
                    (SELECT COUNT(*) FROM students_has_intakes WHERE `intakes_intake_id`='" . $id . "' ) AS student_count,
                    (SELECT COUNT(*) FROM lecturers WHERE `subjects_subject_id` IN (SELECT `subject_id` FROM `subjects` WHERE `levels_level_id` IN (SELECT `level_id` FROM `levels` WHERE `intakes_intake_id`='" . $id . "'))) AS lecturers_count,
                    (SELECT COUNT(*) FROM levels WHERE `intakes_intake_id`='" . $id . "') AS levels_count,
                    (SELECT COUNT(*) FROM subjects WHERE `levels_level_id` IN (SELECT `level_id` FROM `levels` WHERE `intakes_intake_id`='" . $id . "')) AS subjects_count,
                    
                    total_payments,
                    awaiting_payments,
                    pending_approval,
                    reject_payments,
                
                    CASE
                        WHEN total_payments >= 1000000 THEN CONCAT(ROUND(total_payments / 1000000, 1), 'M')
                        WHEN total_payments >= 1000 THEN CONCAT(ROUND(total_payments / 1000, 1), 'K')
                        ELSE CAST(total_payments AS CHAR)
                    END AS total_payments_formatted,
                    
                    CASE
                        WHEN awaiting_payments >= 1000000 THEN CONCAT(ROUND(awaiting_payments / 1000000, 1), 'M')
                        WHEN awaiting_payments >= 1000 THEN CONCAT(ROUND(awaiting_payments / 1000, 1), 'K')
                        ELSE CAST(awaiting_payments AS CHAR)
                    END AS awaiting_payments_formatted,
                    
                    CASE
                        WHEN pending_approval >= 1000000 THEN CONCAT(ROUND(pending_approval / 1000000, 1), 'M')
                        WHEN pending_approval >= 1000 THEN CONCAT(ROUND(pending_approval / 1000, 1), 'K')
                        ELSE CAST(pending_approval AS CHAR)
                    END AS pending_approval_formatted,
                    
                    CASE
                        WHEN reject_payments >= 1000000 THEN CONCAT(ROUND(reject_payments / 1000000, 1), 'M')
                        WHEN reject_payments >= 1000 THEN CONCAT(ROUND(reject_payments / 1000, 1), 'K')
                        ELSE CAST(reject_payments AS CHAR)
                    END AS reject_payments_formatted
                FROM payment_totals_cte");


                $cardData = $cardData->fetch_assoc();

                $isHasStudentActionsPermissions = SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $id) || UserPermissions::hasPermission('intake_students', 1, $id);
                $isHasPaymentsActionsPermissions = SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 1, $id) || UserPermissions::hasPermission('intake_payments', 2, $id);
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Intake Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                    <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                    <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    <style>
                        .accordion {
                            --bs-accordion-btn-color: var(--sub-topic);
                        }

                        .accordion-button::after {
                            order: -1;
                            margin-left: 0;
                            margin-right: 20px;
                            transform: rotate(0deg);
                        }

                        .accordion-button:not(.collapsed)::after {
                            transform: rotate(180deg);
                        }

                        .accordion-button {
                            justify-content: flex-start;
                        }

                        .accordion-icon {
                            transition: transform 0.3s ease, color 0.3s ease;
                        }
                    </style>
                </head>

                <body class="overflow-hidden" onload="initializeSelectedIntake(<?= $id ?>);">
                    <ul class="notifications"></ul>
                    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                        <div id="appContainer" class="row p-0">
                            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                <?php
                                include "includes/side-menu.php";
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

                                    <div class="container p-4 m-0" id="skeleton-list">
                                        <?php
                                        include "./includes/components/skeleton-header.php";
                                        include "./includes/components/skeleton-dashboards.php"; ?>
                                    </div>

                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0" id="accordionParent">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg">Intake Details</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?faculty-id=<?= $intakeData['faculties_faculty_id'] ?>'>...</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intakes?programe-id=<?= $intakeData['programme_id'] ?>'><?= $intakeData["programme"] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $intakeData["intake"] ?></a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                    ?>
                                                        <div class="col-2 d-flex align-items-center justify-content-end mt-3 mt-lg-0 m-md-0 text-end">
                                                            <button class="bg-transparent border-0 align-middle" type="button" onclick="window.location.href='intake-permissions?intake-id=<?= $intakeData['intake_id'] ?>'">
                                                                <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme p-0 m-0"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>

                                            <?php
                                            require_once "./includes/Dashboards/BatchDashboardSummary.php";
                                            $dashboardCards = BatchDashboardSummary::getBatchDashboardSummary($id);
                                            ?>

                                            <div class="container-fluid p-0 mb-4">
                                                <div class="row g-3">
                                                    <?php foreach ($dashboardCards as $card) { ?>
                                                        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                                                            <div class="stats-mini-card">
                                                                <div class="stats-mini-top">
                                                                    <div class="stats-mini-left">
                                                                        <div class="stats-mini-icon">
                                                                            <iconify-icon icon="<?= htmlspecialchars($card['icon']) ?>" width="16" height="16"></iconify-icon>
                                                                        </div>
                                                                        <span class="stats-mini-title"><?= htmlspecialchars($card['title']) ?></span>
                                                                    </div>

                                                                    <button type="button" class="stats-mini-menu">
                                                                        <iconify-icon icon="mdi:dots-horizontal" width="16" height="16"></iconify-icon>
                                                                    </button>
                                                                </div>

                                                                <div class="stats-mini-body">
                                                                    <div class="stats-mini-content">
                                                                        <?php
                                                                        if ($card['title'] != "Monthly New Admissions") {
                                                                        ?>
                                                                            <div class="stats-mini-value"><?= htmlspecialchars($card['value']) ?></div>
                                                                        <?php
                                                                        } else {
                                                                        ?>
                                                                            <div class="stats-mini-value"><?= htmlspecialchars($card['value']) ?> <span class="text-dark-orange text-18">+<?= htmlspecialchars($card['multi_class']) ?></span><span class="text-dark-orange text-14 fw-light mx-3">(Multi Class)</span></div>
                                                                        <?php
                                                                        }
                                                                        ?>

                                                                        <div class="stats-mini-meta">
                                                                            <?php if ($card['change'] !== '') { ?>
                                                                                <span class="stats-mini-change <?= htmlspecialchars($card['change_type']) ?>">
                                                                                    <?= htmlspecialchars($card['change']) ?>
                                                                                </span>
                                                                            <?php } ?>
                                                                            <span class="stats-mini-period"><?= htmlspecialchars($card['period']) ?></span>
                                                                        </div>
                                                                    </div>

                                                                    <?php if (!empty($card['chart'])) { ?>
                                                                        <div class="stats-mini-chart" data-chart='<?= json_encode($card['chart']) ?>'></div>
                                                                    <?php } ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            </div>

                                            <div class="accordion accordion-flush p-0 m-0" id="accordionFlush">
                                                <div class="accordion-item mb-3 border-0">
                                                    <h2 class="accordion-header d-flex justify-content-between align-items-center border-0 p-4 table-btn-success">
                                                        <button class="accordion-button collapsed border-0 py-3 px-0 bg-transparent shadow-none flex-grow-1 text-start"
                                                            type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne"
                                                            aria-expanded="false" aria-controls="flush-collapseOne">
                                                            <span class="sub-topic">Levels</span>
                                                        </button>

                                                        <?php if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("intake", 2, $id)) { ?>
                                                            <div class="d-flex align-items-center w-15">
                                                                <button class="custom-btn w-100" data-bs-target="#addLevel" data-bs-toggle="modal">
                                                                    Add New
                                                                </button>
                                                            </div>
                                                        <?php } ?>
                                                    </h2>
                                                    <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
                                                        <div class="accordion-body bg-theme pt-3 px-0 m-0 border-0">
                                                            <div class="col-12 p-0">
                                                                <div class="row p-0 m-0">
                                                                    <?php
                                                                    $levels = Database::search("
                                                                        SELECT 
                                                                            `levels`.*, 
                                                                            COUNT(`subjects`.`levels_level_id`) AS subject_count
                                                                        FROM `levels`
                                                                        LEFT JOIN `subjects` 
                                                                            ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                        WHERE `intakes_intake_id` = '" . $id . "'
                                                                        GROUP BY `levels`.`level_id`
                                                                        ");

                                                                    if ($levels->num_rows > 0) {
                                                                        while ($level = $levels->fetch_assoc()) {
                                                                    ?>
                                                                            <div class="col-12 px-4 py-3 component-theme mb-10">
                                                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                                                    <div class="col-12 col-md-2 col-lg-1 d-flex align-items-center p-0 m-0 g-0">
                                                                                        <?php
                                                                                        if (!empty($level['level_cover']) && file_exists("./assets/imgs/levels-covers/" . $level['level_cover'])) {
                                                                                        ?>
                                                                                            <img src="./assets/imgs/levels-covers/<?= $level['level_cover'] ?>" class="w-100 h-auto" alt="level img">
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="level img">
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </div>

                                                                                    <div class="col-12 col-md-7 col-lg-8 px-0 px-md-3 mt-3 mt-md-0 g-0 d-flex flex-column justify-content-center">
                                                                                        <h6 class="text-15 text-dark-color fw-semibold lh-lg mb-0">
                                                                                            <?= htmlspecialchars($level['level']) ?>
                                                                                        </h6>
                                                                                        <p class="text-14 main-text lh-lg mb-0">
                                                                                            Number of Subjects : <?= htmlspecialchars($level['subject_count']) ?>
                                                                                        </p>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-3 p-0 mt-3 m-md-0 m-lg-0">
                                                                                        <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                                            <?php
                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake', 10)) {
                                                                                            ?>
                                                                                                <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                                    <button class="custom-danger-btn w-100" onclick="loadLevelEditModel('<?= $level['level_id'] ?>','<?= $level['level'] ?>','<?= $level['level_cover'] ?>')">Edit</button>
                                                                                                </div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>

                                                                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                                <button class="custom-btn w-100" onclick="window.location.href='level-details?level-id=<?= $level['level_id']; ?>'">View</button>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php
                                                                        }
                                                                    } else {
                                                                        ?>
                                                                        <div class="col-12 px-4 py-3 component-theme h-100">
                                                                            <div class="row align-items-center justify-content-center h-100">
                                                                                <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }

                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_students', $id)) {
                                                ?>
                                                    <div class="accordion-item mb-3 border-0">
                                                        <h2 class="accordion-header d-flex justify-content-between align-items-center border-0 p-4 table-btn-yellow">
                                                            <button class="accordion-button collapsed border-0 py-3 px-0 bg-transparent shadow-none flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
                                                                <span class="sub-topic">Students</span>
                                                            </button>
                                                            <?php
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $id)) {
                                                            ?> <div class="d-flex align-items-center w-15">
                                                                    <div class="dropdown w-100">
                                                                        <div class="d-flex align-items-center justify-content-center custom-btn fw-normal w-100 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                                            Add New
                                                                        </div>

                                                                        <ul class="dropdown-menu w-100" style="min-width: max-content;">
                                                                            <li><a class="dropdown-item" href="add-intake-to-registered-students?intake-id=<?= $id; ?>">Add Registered Student</a></li>
                                                                            <li><a class="dropdown-item" href="add-student-to-intake?intake-id=<?= $id; ?>">Add New Student</a></li>
                                                                            <li><a class="dropdown-item" href="upload-intake-student-bulk?intake-id=<?= $id; ?>">Add New Students Bulk</a></li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </h2>
                                                        <div id="flush-collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
                                                            <div class="accordion-body p-4">
                                                                <div class="col-12 p-0 component-theme">
                                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                                        <div class="col-12 component-theme mb-4">
                                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                                <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                                        <?php
                                                                                        $studentCount = Database::search("SELECT COUNT(*) as 'count' FROM `students_has_intakes` WHERE `intakes_intake_id` = '" . $id . "'");
                                                                                        $data = $studentCount->fetch_assoc();
                                                                                        ?>
                                                                                        <span class="text-14 dark-theme fw-medium"><?= $data["count"]; ?> Students</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-8 <?= $isHasStudentActionsPermissions ? 'col-md-6 col-lg-7' : 'col-md-8 col-lg-9' ?> d-flex flex-column">
                                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="filterIntakeHasStudents('<?= $id ?>')">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="<?= $isHasStudentActionsPermissions ? 'col-6 col-md-1 col-lg-1' : 'col-12 col-md-2 col-lg-1' ?> d-flex flex-column justify-content-end">
                                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsFilter" aria-expanded="false" aria-controls="studentsFilter">
                                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                                    </button>
                                                                                </div>
                                                                                <div class="<?= $isHasStudentActionsPermissions ? 'col-6 col-md-3 col-lg-2' : 'd-none' ?> d-flex flex-column justify-content-end">
                                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="downloadIntakeStudentsCSV(<?= $id; ?>)">
                                                                                        Students
                                                                                        <iconify-icon icon="line-md:download-loop" width="24" height="24" class="text-white mx-2"></iconify-icon>
                                                                                    </button>
                                                                                </div>

                                                                                <div class="col-12 collapse" id="studentsFilter">
                                                                                    <div class="card card-body border-0 p-0">
                                                                                        <div class="col-12">
                                                                                            <div class="row">
                                                                                                <?php
                                                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("intake_students", 4)) {
                                                                                                ?>
                                                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                                        <select class="form-select custom-select" aria-label="Default select example" id="update-selector" onchange="filterIntakeHasStudents('<?= $id ?>')">
                                                                                                            <option selected value="0">- Update Students -</option>
                                                                                                            <option value="1">Active Users</option>
                                                                                                            <option value="2">Inactive Users</option>
                                                                                                        </select>
                                                                                                    </div>
                                                                                                    <div class="col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end">
                                                                                                        <button class="custom-btn w-100" type="button" onclick="applyIntakeHasStudentsUpdate(<?= $id ?>)">Apply</button>
                                                                                                    </div>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                                <div class="col-12 offset-0 offset-md-2 offset-lg-6 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                                    <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleIntakeHasStudentPagination('<?= $id ?>','filter')">
                                                                                                        <option selected value="0">- Filter Data -</option>
                                                                                                        <option value="1">Active</option>
                                                                                                        <option value="2">Inactive</option>
                                                                                                    </select>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                                    <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleIntakeHasStudentPagination('<?= $id ?>','filter')">
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
                                                                            <div class="table-responsive overflow-visible" id="students-table-container">
                                                                                <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                                                    <thead>
                                                                                        <tr class="br-t-5">
                                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                                <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                                    <div class="d-flex justify-content-between align-items-center w-100" onclick="handleIntakeHasStudentPagination(<?= $id ?>,'sortByName')">
                                                                                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </th>
                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasStudentPagination(<?= $id ?>,'sortById')">
                                                                                                <div class="d-flex flex-row justify-content-between align-items-center cursor-pointer">
                                                                                                    <span class="text-14 main-text fw-medium me-2">Student Id</span>
                                                                                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                </div>
                                                                                            </th>
                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Mobile</th>
                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasStudentPagination(<?= $id ?>,'sortByDate')">
                                                                                                <div class="d-flex flex-row justify-content-between align-items-center cursor-pointer">
                                                                                                    <span class="text-14 main-text fw-medium me-2">Admission Date</span>
                                                                                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                </div>
                                                                                            </th>

                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>

                                                                                            <?php
                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 1)) {
                                                                                            ?>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Action</th>
                                                                                            <?php
                                                                                            }
                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $id) || UserPermissions::hasPermission('intake_students', 10, $id)) {
                                                                                            ?>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody id="user-table-data">
                                                                                        <?php
                                                                                        $query = "SELECT 
                                                                            `shi`.`students_has_intakes_id` AS `shiId`,
                                                                            `s`.`student_id` AS `sId`,
                                                                            `shi`.`student_id`,
                                                                            `ld`.`first_name`,
                                                                            `ld`.`last_name`,
                                                                            `ld`.`email`,
                                                                            `s`.`mobile`,
                                                                            `s`.`profile_img`,
                                                                            `shi`.`addmission_date`,
                                                                            `t`.`title`,
                                                                            `st`.`status_id`,
                                                                            `st`.`status`,
                                                                            `ld`.`verification`,
                                                                            `ld`.`reset_time`
                                                                        FROM `students_has_intakes` AS `shi`
                                                                        INNER JOIN `students` AS `s` 
                                                                            ON `shi`.`students_student_id` = `s`.`student_id`
                                                                        INNER JOIN `login_details` AS `ld`
                                                                            ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                                                                        INNER JOIN `intakes` AS `i` 
                                                                            ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                                                                        INNER JOIN `programmes` AS `p` 
                                                                            ON `i`.`programmes_programme_id` = `p`.`programme_id`
                                                                        INNER JOIN `faculties` AS `f` 
                                                                            ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
                                                                        INNER JOIN `title` AS `t` 
                                                                            ON `s`.`title_title_id` = `t`.`title_id`
                                                                        INNER JOIN `status` AS `st` 
                                                                            ON `shi`.`status_status_id` = `st`.`status_id`
                                                                        WHERE `shi`.`intakes_intake_id` = '" . $id . "'
                                                                        ORDER BY `shi`.`students_has_intakes_id` DESC";

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
                                                                                        ?>
                                                                                                <tr>
                                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["shiId"]; ?>" onchange="checkRowEvents(<?= $data['shiId']; ?>);">
                                                                                                            <?php
                                                                                                            if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                                            ?>
                                                                                                                <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                                                            <?php
                                                                                                            } else {
                                                                                                                $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                                $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                                            ?>
                                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                            <?php
                                                                                                            }
                                                                                                            ?>
                                                                                                            <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["student_id"]; ?></span></td>
                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["email"]; ?></span></td>
                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["mobile"]; ?></span></td>
                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["addmission_date"])); ?></span></td>

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
                                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 1)) {
                                                                                                    ?>
                                                                                                        <td class="pt-3 pb-3 align-middle">
                                                                                                            <button class="table-btn table-btn-dark-theme active w-100" onclick="window.location.href='edit-intake-student?student-id=<?= $data['shiId']; ?>'">View</button>
                                                                                                        </td>
                                                                                                    <?php
                                                                                                    }
                                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $id) || UserPermissions::hasPermission('intake_students', 10, $id)) {
                                                                                                    ?>
                                                                                                        <td class="py-3 align-middle">
                                                                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                            <div class="dropdown">
                                                                                                                <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">

                                                                                                                    <?php
                                                                                                                    if (!empty($data['verification']) && empty($data['reset_time'])) {
                                                                                                                    ?>
                                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendStudentRegistrationEmail('<?php echo $data['sId']; ?>')">
                                                                                                                                <iconify-icon icon="lets-icons:send-fill" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                Resend Registration Link</a></li>
                                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                    <?php
                                                                                                                    } else {
                                                                                                                    ?>
                                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendStudentRegistrationEmail('<?php echo $data['sId']; ?>')">
                                                                                                                                <iconify-icon icon="solar:password-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                Resend Password Reset Link</a></li>
                                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                    <?php
                                                                                                                    }
                                                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 10, $id)) {
                                                                                                                    ?>
                                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="edit-intake-student?student-id=<?= $data['shiId']; ?>">
                                                                                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                Edit</a></li>
                                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                    <?php
                                                                                                                    }
                                                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $id)) {
                                                                                                                    ?>
                                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="intake-student-payments?student-id=<?= $data['shiId']; ?>">
                                                                                                                                <iconify-icon icon="iconoir:wallet" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                Payments</a></li>
                                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                        <?php
                                                                                                                    }
                                                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 4, $id)) {

                                                                                                                        if ($data["status"] == "Active") {
                                                                                                                        ?>
                                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateIntakeStudentStatus(2,<?= $id ?>,'<?php echo $data['shiId']; ?>')">
                                                                                                                                    <iconify-icon icon="lsicon:minus-filled" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    Deactivate</a></li>
                                                                                                                        <?php
                                                                                                                        } else if ($data["status"] == "Inactive") {
                                                                                                                        ?>
                                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateIntakeStudentStatus(1,<?= $id ?>,'<?php echo $data['shiId']; ?>')">
                                                                                                                                    <iconify-icon icon="dashicons:plus-alt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    Activate</a></li>
                                                                                                                    <?php
                                                                                                                        }
                                                                                                                    }
                                                                                                                    ?>
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
                                                                                <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container3">
                                                                                    <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                                        <div class="row p-4 align-items-center">
                                                                                            <!-- Previous Button (col-1) -->
                                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleIntakeHasStudentPagination(<?= $id ?>,'previousPage');">
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
                                                                                                        $action = $isActive ? '' : "onclick=\"handleIntakeHasStudentPagination('{$id}','goToPage', '{$i}')\"";
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
                                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleIntakeHasStudentPagination(<?= $id ?>,'nextPage',<?= $number_of_pages ?>);">
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
                                                <?php
                                                }

                                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $id)) {
                                                ?>
                                                    <div class="accordion-item mb-3 border-0">
                                                        <h2 class="accordion-header d-flex justify-content-between align-items-center border-0 p-4 table-btn-pink">
                                                            <button class="accordion-button collapsed border-0 py-3 px-0 bg-transparent shadow-none flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseThree" aria-expanded="false" aria-controls="flush-collapseThree">
                                                                <span class="sub-topic flex-grow-1">Payments</span>
                                                            </button>
                                                            <?php
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 2, $id)) {
                                                            ?>

                                                                <div class="d-flex align-items-center w-15">
                                                                    <div class="dropdown w-100">
                                                                        <div class="d-flex align-items-center justify-content-center custom-btn fw-normal w-100 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                                            Actions
                                                                        </div>
                                                                        <ul class="dropdown-menu w-100" style="min-width: max-content;">
                                                                            <li><a class="dropdown-item" onclick="window.location = 'intake-payment-plans?intake-id=<?= $id ?>'">Intake Payment Plans</a></li>
                                                                            <li><a class="dropdown-item" onclick="window.location = 'assign-payment-plan-to-students?intake-id=<?= $id ?>'">Assign Payment Plan to Students</a></li>

                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </h2>
                                                        <div id="flush-collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
                                                            <div class="accordion-body p-4">
                                                                <div class="col-12 p-0 component-theme">
                                                                    <div class="row p-0 m-0 g-0">
                                                                        <div class="col-12 mb-4">
                                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                                <div class="col-12 col-md-2 d-flex flex-column">
                                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                                        <?php
                                                                                        $query = "
                                                                        SELECT 
                                                                            COUNT(*) as 'count'
                                                                            FROM `course_payments` AS `cp`
                                                                        INNER JOIN `payment_status` AS `ps` 
                                                                            ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                       
                                                                        INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi` 
                                                                            ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                                                                        INNER JOIN `students_has_intakes` AS `shi` 
                                                                            ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                                        INNER JOIN `students` AS `s` 
                                                                            ON `shi`.`students_student_id` = `s`.`student_id`
                                                                        INNER JOIN `title` AS `t` 
                                                                            ON `s`.`title_title_id` = `t`.`title_id`
                                                                        
                                                                        WHERE `shi`.`intakes_intake_id` = '" . $id . "'
                                                                        AND `cp`.`payment_status_payment_status_id`='1'
                                                                        ";
                                                                                        $payments_count = Database::search($query);
                                                                                        $data = $payments_count->fetch_assoc();
                                                                                        ?>
                                                                                        <span class="text-14 dark-theme fw-medium"><?= $data['count']; ?> Awaiting Payments</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-12 <?= $isHasStudentActionsPermissions ? 'col-md-6 col-lg-7' : 'col-md-8 col-lg-9' ?> d-flex flex-column">
                                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="payment-search-text" onkeyup="handleIntakeHasPaymentsPagination(<?= $id ?>,'filter')">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="<?= $isHasStudentActionsPermissions ? 'col-6 col-md-1 col-lg-1' : 'col-12 col-md-2 col-lg-1' ?> d-flex flex-column justify-content-end">
                                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#intakePaymentFilter" aria-expanded="false" aria-controls="intakePaymentFilter">
                                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                                    </button>
                                                                                </div>
                                                                                <div class="<?= $isHasStudentActionsPermissions ? 'col-6 col-md-3 col-lg-2' : 'd-none' ?> d-flex flex-column justify-content-end">
                                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="downloadIntakePaymentsReport(this,<?= $id; ?>,'<?= $intakeData['intake']  ?>')">
                                                                                        Payments
                                                                                        <iconify-icon icon="line-md:download-loop" width="24" height="24" class="text-white mx-2"></iconify-icon>
                                                                                    </button>
                                                                                </div>
                                                                                <div class="col-12 collapse collapse-horizontal" id="intakePaymentFilter">
                                                                                    <div class="row justify-content-end">
                                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                            <select class="form-select custom-select" aria-label="Default select example" id="payment-category-selector" onchange="handleIntakeHasPaymentsPagination(<?= $id ?>,'filter')">
                                                                                                <option selected value="0">- Payment Category -</option>
                                                                                                <?php
                                                                                                $paymentCategories = Database::search("SELECT * FROM `payment_category`");
                                                                                                while ($paymentCategory = $paymentCategories->fetch_assoc()) {
                                                                                                ?>
                                                                                                    <option value="<?= $paymentCategory['payment_category_id'] ?>"><?= $paymentCategory['payment_category'] ?></option>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                            </select>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                            <select class="form-select custom-select" aria-label="Default select example" id="payment-status-selector" onchange="handleIntakeHasPaymentsPagination(<?= $id ?>,'filter')">
                                                                                                <option selected value="0">- Payment Status -</option>
                                                                                                <?php
                                                                                                $paymentStatus = Database::search("SELECT * FROM `payment_status`");
                                                                                                while ($status = $paymentStatus->fetch_assoc()) {
                                                                                                ?>
                                                                                                    <option value="<?= $status['payment_status_id'] ?>"><?= $status['payment_status'] ?></option>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                            </select>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                            <select class="form-select custom-select" aria-label="Default select example" id="payment-sort-selector" onchange="handleIntakeHasPaymentsPagination(<?= $id ?>,'filter')">
                                                                                                <option selected value="0">- Payment Method -</option>
                                                                                                <option value="1">Bank Transfer</option>
                                                                                                <option value="2">Card Payments</option>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                                    <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="payment-show-result-selector" onchange="handleIntakeHasPaymentsPagination(<?= $id ?>,'filter')">
                                                                                        <option selected value="10">10</option>
                                                                                        <option value="25">25</option>
                                                                                        <option value="50">50</option>
                                                                                        <option value="100">100</option>
                                                                                        <option value="250">250</option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-12">
                                                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                                                <div class="table-responsive-wrapper">
                                                                                    <div class="table-responsive overflow-visible" id="payments-table-container">
                                                                                        <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                                                                                            <thead>
                                                                                                <tr class="br-t-5">
                                                                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                                        <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'sortByName')">
                                                                                                                <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    </th>

                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'sortById')">
                                                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                                            <span class="text-14 main-text fw-medium me-2">Student Intake Id</span>
                                                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                        </div>
                                                                                                    </th>

                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                                                                                        <span class="text-14 main-text fw-medium me-2">Payment Type</span>
                                                                                                    </th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'sortByDate')">
                                                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                                            <span class="text-14 main-text fw-medium me-2">Due Date</span>
                                                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                        </div>
                                                                                                    </th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'sortByAmount')">
                                                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                                            <span class="text-14 main-text fw-medium me-2">Amount</span>
                                                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                        </div>
                                                                                                    </th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Paid Amount</th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Balance Amount</th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>

                                                                                                    <?php

                                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $id)) {
                                                                                                    ?>
                                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                </tr>
                                                                                            </thead>
                                                                                            <tbody id="user-table-data">
                                                                                                <?php

                                                                                                $query = "SELECT
                                                                            `cp`.`course_payments_id`,
                                                                            `ps`.`payment_status`,
                                                                            `ps`.`payment_status_id`,
                                                                           `cp`.`due_date`,
                                                                            `cp`.`amount`,
                                                                           
                                                                            `cp`.`discount`,
                                                                            `cp`.`discount_type_discount_type_id`,
                                                                            `shi`.`student_id`,
                                                                            `ld`.`first_name`,
                                                                            `ld`.`last_name`,
                                                                            `t`.`title`,
                                                                            `s`.`profile_img`,
                                                                            `c`.`code`,
                                                                            `c`.`decimal_places`,
                                                                            `pt`.`payment_type`,
                                                                            IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                                                            IFNULL(cpp.paid_amount, 0) AS paid_amount,
                                                                            IFNULL(cpp.total_discount, 0) AS total_discount,
                                                                            IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                                                                            FROM `course_payments` AS `cp`
                                                                        INNER JOIN `payment_status` AS `ps` 
                                                                            ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                       
                                                                        INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi` 
                                                                            ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                                                                            INNER JOIN `payment_category` AS `pc`
                                                                            ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                                                            INNER JOIN `currencies` AS `c`
                                                                            ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                                                        INNER JOIN `payment_type` AS `pt`
                                                                            ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`    
                                                                        INNER JOIN `students_has_intakes` AS `shi` 
                                                                            ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                                        INNER JOIN `students` AS `s` 
                                                                            ON `shi`.`students_student_id` = `s`.`student_id`
                                                                        INNER JOIN `login_details` AS `ld`
                                                                            ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                                                                        INNER JOIN `title` AS `t` 
                                                                            ON `s`.`title_title_id` = `t`.`title_id`
                                                                       LEFT JOIN (
                                                                            SELECT
                                                                                course_payments_course_payments_id,
                                                                                SUM(amount) AS panelty_amount
                                                                            FROM panelty_payments
                                                                            GROUP BY course_payments_course_payments_id
                                                                        ) pp
                                                                            ON cp.course_payments_id = pp.course_payments_course_payments_id
                                                                    
                                                                        LEFT JOIN (
                                                                            SELECT
                                                                                course_payments_course_payments_id,
                                                                                SUM(paid_amount) AS paid_amount,
                                                                                SUM(discount_amount) AS total_discount,
                                                                                SUM(panelty_amount) AS total_sub_panelty_amount
                                                                            FROM course_payment_has_payments
                                                                            WHERE payment_status_payment_status_id IN (2,3,5,6,8)
                                                                            GROUP BY course_payments_course_payments_id
                                                                        ) cpp
                                                                            ON cp.course_payments_id = cpp.course_payments_course_payments_id 
                                                                        WHERE `shi`.`intakes_intake_id` = '" . $id . "'
                                                                        GROUP BY `cp`.`course_payments_id`
                                                                        ORDER BY `cp`.`course_payments_id` DESC
                                                                        ";

                                                                                                $results = Database::search($query);
                                                                                                $pageno = 1;
                                                                                                $results_count = $results->num_rows;
                                                                                                $result_per_page = 10;

                                                                                                $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;
                                                                                                $page_results = ($pageno - 1) * $result_per_page;

                                                                                                $query_paginated = $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results;
                                                                                                $users = Database::search($query_paginated);

                                                                                                if ($users->num_rows > 0) {
                                                                                                    while ($data = $users->fetch_assoc()) {
                                                                                                        $discountAmount = $data["discount"];
                                                                                                        if ($data["discount_type_discount_type_id"] == 3) {
                                                                                                            $discountAmount = $data["amount"] / 100 * $data["discount"];
                                                                                                        }
                                                                                                        $payableAmount = $data["amount"] - $discountAmount - $data["total_discount"] + $data["panelty_amount"] + $data["total_sub_panelty_amount"];
                                                                                                        $balance = $payableAmount - $data["paid_amount"];
                                                                                                ?>
                                                                                                        <tr>
                                                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                                    <?php
                                                                                                                    if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                                                    ?>
                                                                                                                        <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                                                    <?php
                                                                                                                    } else {
                                                                                                                        $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                                        $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                                                    ?>
                                                                                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                                    <?php
                                                                                                                    }
                                                                                                                    ?>
                                                                                                                    <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                                                </div>
                                                                                                            </td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["student_id"]; ?></span></td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["payment_type"]; ?></span></td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["due_date"] != '' ? date("Y-m-d", strtotime($data["due_date"])) : 'N/A'; ?></span></td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"> <?= $data['code'] . " " . number_format($payableAmount, 00); ?></span></td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($data["paid_amount"], 00) ?></span></td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($balance, 00); ?></span></td>
                                                                                                            <td class="py-2 align-middle">
                                                                                                                <?php
                                                                                                                $status_classes = [
                                                                                                                    "0"     => "table-btn-purple",
                                                                                                                    "1"  => "table-btn-yellow",

                                                                                                                    "9"  => "table-btn-success",
                                                                                                                ];
                                                                                                                $sbutton_class = $status_classes[$data["payment_status_id"]];
                                                                                                                ?>
                                                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_status"]; ?></button>
                                                                                                            </td>
                                                                                                            <?php

                                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $id)) {
                                                                                                            ?>
                                                                                                                <td class="py-3 align-middle">
                                                                                                                    <div class="dropdown">
                                                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-student-payment?payment-id=<?= $data['course_payments_id'] ?>">
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
                                                                                        <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container4">
                                                                                            <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                                                <div class="row p-4 align-items-center">
                                                                                                    <!-- Previous Button (col-1) -->
                                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'previousPage')">
                                                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                                                                                    </button>
                                                                                                    <!-- Page Numbers (col-10, centered) -->
                                                                                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                                        <ul class="pagination-list d-flex mb-0">
                                                                                                            <?php
                                                                                                            $shudderPages1 = false;
                                                                                                            $shudderPages2 = false;

                                                                                                            function renderPaymentPageButton($catId, $i, $isActive = false)
                                                                                                            {
                                                                                                                $activeClass = $isActive ? 'active' : '';
                                                                                                                $action = $isActive ? '' : "onclick=\"handleIntakeHasPaymentsPagination('{$catId}','goToPage', '{$i}')\"";
                                                                                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                                                            }

                                                                                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                                                    renderPaymentPageButton($id, $i, $pageno == $i);
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
                                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                                ?>
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
                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake', 2, $id)) {
                    ?>
                        <!-- level Add Model => Start -->
                        <div class="modal fade br-0" id="addLevel" aria-hidden="true" aria-labelledby="addLevelLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="addLevelLabel">Add Level</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Level Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="level-name">
                                                    <span class="text-12 text-dark-red" id="al-name-feedback"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text">Level Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="level cover" id="level-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-4 col-xl-3 d-flex mt-3 mt-lg-0">
                                                            <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Upload your level cover here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Supported format:</p>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpeg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">or</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.png</span>
                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-1 mt-md-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('cover-input','level-cover');">
                                                                        Upload Image
                                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                    </button>
                                                                    <!-- Hidden file input -->
                                                                    <input type="file" id="cover-input" class="d-none" accept="image/*" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-5 col-xl-6 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                            <button class="col-12 col-lg-8 custom-btn m-0" onclick="addLevel(this,<?= $id; ?>)">Save Level</button>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red" id="al-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Level Add Model => End -->
                    <?php
                    }
                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake', 10, $id)) {
                    ?>
                        <!-- Level Edit Modal => Start -->
                        <div class="modal fade br-0" id="editLevel" aria-hidden="true" aria-labelledby="editLevelLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editLevelLabel">Edit Level</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Level Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="edit-level-name">
                                                    <span class="text-12 text-dark-red" id="el-name-feedback"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column justify-content-end">
                                                    <label for="" class="text-14 main-text">Level Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/courses-thumbnails/image.png" class="w-100 h-auto" alt="edit level cover" id="edit-level-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex mt-3 mt-lg-0">
                                                            <div class="justify-content-center g-0 p-0 m-0 flex-grow-1 d-flex flex-column">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Upload your payment slip here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Supported format:</p>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpeg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">or</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.png</span>
                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('edit-cover-input','edit-level-cover');">
                                                                        Select another image
                                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                        <!-- Hidden file input -->
                                                                        <input type="file" id="edit-cover-input" class="d-none" accept="image/*" />
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-5 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                            <button class="col-12 col-lg-8 custom-btn m-0" id="save-edit">Save Changes</button>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red" id="el-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Level Edit Modal => End -->
                    <?php
                    }

                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 1, $id)) {
                    ?>
                        <div class="modal fade br-0" id="csvDownload" aria-hidden="true" aria-labelledby="csvDownloadLabel" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="csvDownloadLabel">Download Students CSV</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <!-- Waiting Message and Animation -->
                                        <div id="waitingMessage" class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <h3 class="mt-3">Preparing Your CSV File...</h3>
                                            <p class="text-muted">Please wait while we generate your download.</p>
                                        </div>

                                        <!-- Success Message (Hidden by Default) -->
                                        <div id="successMessage" class="text-center" class="d-none">
                                            <div class="checkmark-circle">
                                                <div class="checkmark"></div>
                                            </div>
                                            <h3 class="mt-3">Download Ready!</h3>
                                            <p class="text-muted">Your CSV file has been successfully generated.</p>
                                            <button class="custom-btn dark-theme px-5 mt-3 text-white" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
                    }

                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('intake', 2, $id) || UserPermissions::hasPermission('intake', 10, $id)) {
                    ?>
                        <!-- cover crop modal => Start -->
                        <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Crop Image</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-center d-flex  align-items-center justify-content-center">
                                        <img id="image" class="img-fluid bg-black" style="width: 75%;height: auto;" />
                                    </div>
                                    <div class="modal-footer">
                                        <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- cover crop modal => End -->
                    <?php
                    }
                    ?>
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
    }
} ?>