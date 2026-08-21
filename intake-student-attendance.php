<?php
require_once './includes/UserClass.php';
require_once "./includes/connection.php";
require_once "./includes/UserPermissionsClass.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-id']) && !empty($_GET['student-id']))) {
            header("Location: ./users");
        } else {

            $id = $_GET['student-id'];

            $studentHasIntake = Database::search("SELECT
                `faculty`,
                `faculty_id`,
                `programme`,
                `programme_id`,
                `intake`,
                `intake_id`,
                `students_has_intakes`.`student_id`,
                `students`.`student_id` AS `admissionId`,
                `first_name`,
                `last_name`,
                `title`,
                `students_has_intakes_id`
            FROM `students_has_intakes`
            INNER JOIN `students` 
                ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
            INNER JOIN `login_details`
                ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`    
            INNER JOIN `title` 
                ON `students`.`title_title_id` = `title`.`title_id`
            INNER JOIN `intakes` 
                ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
             
            INNER JOIN `programmes` 
                ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` 
                ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $id . "'
        ");


            if ($studentHasIntake->num_rows == 0) {
                header("Location: ./students");
            } else {
                $studentHasIntakeData = $studentHasIntake->fetch_assoc();

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('course_overview'))) {
                    header("Location: ./faculties");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Batch Student Assignments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                          <link rel="stylesheet" href="./assets/css/calender.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/newCalender.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden" onload="getBatchStudentAttendanceData(<?= $id ?>);">
                        <div class="notifications"></div>
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
                                            selectItem::setSelected("Students");
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
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Student Assignments - Admission Number : <?= $studentHasIntakeData['admissionId'] ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $studentHasIntakeData['faculty_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $studentHasIntakeData['programme_id'] ?>'><?= $studentHasIntakeData['programme']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='batch-details?batch-id=<?= $studentHasIntakeData['intake_id'] ?>'><?= $studentHasIntakeData['intake']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo $studentHasIntakeData['title'] . "." . $studentHasIntakeData['first_name'] . " " . $studentHasIntakeData['last_name']; ?></a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php
                                                $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));
                                                $totalAttendanceQuery = "
                                        SELECT 
                                            SUM(CASE 
                                                WHEN a.attendance_status_attendance_status_id = 2  THEN 1
                                                ELSE 0
                                            END) AS present_count,
                                            COUNT(tt.time_table_id) AS total_count                                                                                                                                                                
                                                                               
                                        FROM 
                                            subjects AS subj
                                        INNER JOIN 
                                            time_table AS tt ON subj.subject_id = tt.subjects_subject_id
                                        LEFT JOIN 
                                            attendance AS a ON tt.time_table_id = a.time_table_time_table_id
                                                AND a.students_student_id = '" . $studentHasIntakeData['admissionId'] . "'
                                        WHERE 
                                            subj.levels_level_id IN (
                                                SELECT level_id 
                                                FROM levels 
                                                WHERE intakes_intake_id = '" . $studentHasIntakeData['intake_id'] . "'
                                            )
                                            AND (
                                                subj.subject_type_subject_type_id = 1
                                                OR (
                                                    subj.subject_type_subject_type_id = 2
                                                    AND EXISTS (
                                                        SELECT 1 
                                                        FROM students_has_subjects shs 
                                                        WHERE shs.students_student_id = '" . $studentHasIntakeData['admissionId'] . "'
                                                        AND shs.subjects_subject_id = subj.subject_id
                                                    )
                                                )
                                            )
                                            AND tt.time_table_status_time_table_status_id = 1
                                            AND lecture_type_lecture_type_id != 3
                                         AND TIMESTAMP(tt.date, tt.start_time) <= '" . $date->format('Y-m-d H:i:s') . "'
                                           
                                    ";

                                                $totalAttendanceData = Database::search($totalAttendanceQuery)->fetch_assoc();
                                                $totalLectures = $totalAttendanceData["total_count"] ? $totalAttendanceData["total_count"] : 0;
                                                $totalPresent = $totalAttendanceData["present_count"] ? $totalAttendanceData["present_count"] : 0;
                                                $totalAbsent = $totalAttendanceData["total_count"] - $totalPresent;

                                                ?>

                                                <div class="col-12 component-theme">
                                                    <div class="row p-4 m-0 g-0">
                                                        <span class="main-topic lh-lg">Summary of Attendance</span>
                                                        <div class="container p-0 mt-2 g-0">
                                                            <div class="row g-4 d-flex">
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                    <div class="bg-light-theme p-3 p-md-4  w-100">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <label class="flex-grow-1 text-16 sub-text-color">Attended Lectures</label>
                                                                            <iconify-icon icon="gg:check-o" height="35" class="dark-theme"></iconify-icon>
                                                                        </div>
                                                                        <div class="mt-2">
                                                                            <span class="text-24 dark-color fw-semibold lh-lg"><?php echo $totalPresent > 0 ? $totalPresent : 0; ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                    <div class="bg-light-theme p-3 p-md-4  w-100">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <label class="flex-grow-1 text-16 sub-text-color">Absent Lectures</label>
                                                                            <iconify-icon icon="gg:close-o" width="35" height="35" class="dark-theme"></iconify-icon>
                                                                        </div>
                                                                        <div class="mt-2">
                                                                            <span class="text-24 dark-color fw-semibold lh-lg"><?php echo $totalAbsent; ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                    <div class="bg-light-theme p-3 p-md-4  w-100">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <label class="flex-grow-1 text-16 sub-text-color">Lectures Attended Percentage</label>
                                                                            <iconify-icon icon="uil:heart-rate" width="35" height="35" class="dark-theme"></iconify-icon>
                                                                        </div>
                                                                        <div class="mt-2">
                                                                            <span class="text-24 dark-color fw-semibold lh-lg"><?php echo $totalPresent > 0 ? round(($totalPresent / $totalLectures) * 100) : 0; ?>%</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                                                    <div class="bg-light-theme p-3 p-md-4  w-100">
                                                                        <div class="d-flex flex-row align-items-center">
                                                                            <label class="flex-grow-1 text-16 sub-text-color">Lectures Absent Percentage</label>
                                                                            <iconify-icon icon="uil:heart-rate" width="35" height="35" class="dark-theme"></iconify-icon>
                                                                        </div>
                                                                        <div class="mt-2">
                                                                            <span class="text-24 dark-color fw-semibold lh-lg"><?php echo $totalAbsent > 0 ?  round(($totalAbsent / $totalLectures) * 100) : 0; ?>%</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        
                                                    </div>
                                                </div>

                                                <div class="col-12 py-4 px-4 pe-4 mt-4 component-theme">
                                                    <div class="row p-0 m-0 gy-4 gx-3">
                                                        <div class="container">
                                                            <div class="col-12 d-flex flex-column flex-lg-row justify-content-center mb-4 mt-2">
                                                                <div class="col-2 col-lg-1 d-none d-lg-block">
                                                                    <button class="w-100 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme privious-button" onclick="prevMonth(2)">
                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                        <span class="text-14 main-text fw-semibold">Privious</span>
                                                                    </button>
                                                                </div>
                                                                <div class="col-12 col-lg-10 text-center">
                                                                    <h4 class="text-center" id="monthYear">February</h4>
                                                                </div>
                                                                <div class="col-12 col-lg-1 mt-3 d-block d-lg-none">
                                                                    <div class="row">
                                                                        <div class="col-6 d-flex justify-content-start">
                                                                            <button class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center component-theme privious-button" onclick="prevMonth(2)">
                                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                                <span class="text-14 main-text fw-semibold">Privious</span>
                                                                            </button>
                                                                        </div>
                                                                        <div class="col-6 d-flex justify-content-end">
                                                                            <button class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="nextMonth(2)">
                                                                                <span class="text-14 main-text fw-semibold">Next</span>
                                                                                <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6 col-lg-1 text-end d-none d-lg-block">
                                                                    <button class="w-100 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="nextMonth(2)">
                                                                        <span class="text-14 main-text fw-semibold">Next</span>
                                                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div id="calendar" class="calendar"></div>
                                                        </div>

                                                        <!-- Event Modal -->
                                                        <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="eventModalLabel">Events on <span id="selectedDate"></span></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body" id="eventDetails"></div>
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
                        <script src="./assets/js/eventCalender.js?v=<?= Project::getVersion() ?>"></script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>