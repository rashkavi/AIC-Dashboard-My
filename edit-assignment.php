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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['assignment-id']) && !empty($_GET['assignment-id']))) {
            header("Location: ./subject-assignments");
        } else {

            $id = $_GET['assignment-id'];
            $assignment = Database::search("SELECT * FROM `assignments`
            INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
            INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` 
            WHERE `assignment_id` = '" . $id . "'");
            if ($assignment->num_rows == 0) {
                header("Location: ./subject-assignments");
            } else {
                $assignmentData = $assignment->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::validPermisisonType('assignments', 10, $id))) {
                    header("Location: ./subject-assignments?subject-id=" . $assignmentData['subjects_subject_id']);
                } else {

                    $assignmentMarkReleased = Database::search("SELECT COUNT(`lecturers_has_students_has_assignments_id`) AS 'count' FROM `lecturers_has_students_has_assignments` 
                WHERE `students_has_assignments_students_has_assignments_id` IN (SELECT `students_has_assignments_id` 
                FROM `students_has_assignments` WHERE `assignments_assignment_id`='" . $assignmentData['assignment_id'] . "')")->fetch_assoc()['count'];

                    $timezone = new DateTimeZone("Asia/Colombo");
                    $today = new DateTime("now", $timezone);
                    $todayDate = $today->format('Y-m-d H:i:s');

                    $endDateObj = new DateTime($assignmentData["end_date"], $timezone);

                    // Stored values split for the inputs ('' when not set)
                    $startDateValue = $startTimeValue = $endDateValue = $endTimeValue = '';
                    $lateDateValue = $lateTimeValue = $recDateValue = $recTimeValue = '';

                    if (!empty($assignmentData['start_date'])) {
                        $dt = new DateTime($assignmentData['start_date']);
                        $startDateValue = $dt->format('Y-m-d');
                        $startTimeValue = $dt->format('H:i');
                    }
                    if (!empty($assignmentData['end_date'])) {
                        $dt = new DateTime($assignmentData['end_date']);
                        $endDateValue = $dt->format('Y-m-d');
                        $endTimeValue = $dt->format('H:i');
                    }
                    if (!empty($assignmentData['late_submission_end_date'])) {
                        $dt = new DateTime($assignmentData['late_submission_end_date']);
                        $lateDateValue = $dt->format('Y-m-d');
                        $lateTimeValue = $dt->format('H:i');
                    }
                    if (!empty($assignmentData['recorrection_apply_end_date'])) {
                        $dt = new DateTime($assignmentData['recorrection_apply_end_date']);
                        $recDateValue = $dt->format('Y-m-d');
                        $recTimeValue = $dt->format('H:i');
                    }

                    // Current recorrection fee = latest history row
                    $recorrectionFeeValue = '';
                    $latestFee = Database::search("SELECT `recorrection_fee` FROM `assignment_recorrection_fee` 
                        WHERE `assignments_assignment_id`='" . $assignmentData['assignment_id'] . "' ORDER BY `recorrection_fee_id` DESC LIMIT 1");
                    if ($latestFee->num_rows > 0) {
                        $recorrectionFeeValue = $latestFee->fetch_assoc()['recorrection_fee'];
                    }
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Update Assignments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/translations.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden">
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
                                            selectItem::setSelected("Course Overview");
                                            ?>
                                        </div>
                                        <div class="container p-4 m-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-add-edit.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row align-items-center p-0 m-0 g-0">
                                                        <span class="main-topic lh-lg">Update Assignment - #<?php echo $assignmentData['assignment_code'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./programes?faculty-id=<?= $assignmentData['faculty_id'] ?>"><?= $assignmentData['faculty'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subjects?level-id=<?= $assignmentData['level_id'] ?>"><?= $assignmentData['level'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $assignmentData['subject_id'] ?>"><?= $assignmentData['subject'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-assignments?subject-id=<?= $assignmentData['subject_id'] ?>">Assignments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Update Assignments</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>

                                                <div class="col-12 px-4 py-3 component-theme">
                                                    <div class="row gy-3 align-content-center">
                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Assignment Code</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="code" value="<?php echo htmlspecialchars($assignmentData['assignment_code']) ?>">
                                                            <span class="text-12 text-dark-red" id="code-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-8 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Assignment Topic</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="topic" value="<?php echo htmlspecialchars($assignmentData['assignment']) ?>">
                                                            <span class="text-12 text-dark-red" id="topic-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Start Date & Time</label>
                                                            <div class="col-12 mt-2">
                                                                <div class="row gx-3">
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="date" class="col-6 form-control custom-input" id="start-date" value="<?= $startDateValue ?>">
                                                                        <span class="text-12 text-dark-red" id="start-date-feedback"></span>
                                                                    </div>
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="time" class="col-6 form-control custom-input" id="start-time" value="<?= $startTimeValue ?>">
                                                                        <span class="text-12 text-dark-red" id="start-time-feedback"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-6 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">End Date & Time</label>
                                                            <div class="col-12 mt-2">
                                                                <div class="row gx-3">
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="date" class="col-6 form-control custom-input" id="end-date" onchange="acceptFutureDate('end-date');" value="<?= $endDateValue ?>">
                                                                        <span class="text-12 text-dark-red" id="end-date-feedback"></span>
                                                                    </div>
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="time" class="col-6 form-control custom-input" id="end-time" value="<?= $endTimeValue ?>">
                                                                        <span class="text-12 text-dark-red" id="end-time-feedback"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Assigment Document</label>
                                                            <div class="col-12 px-1 mt-2">
                                                                <div class="row">
                                                                    <?php
                                                                    if (!empty($assignmentData["attachment"]) && file_exists("./assets/uploads/assignments-attachments/" . $assignmentData["attachment"])) {
                                                                    ?>
                                                                        <div class="col-12 col-md-4 my-3 file-item cursor-pointer" onclick="viewAssignment('<?php echo $assignmentData['attachment'] ?>')">
                                                                            <div class="p-3 bl-solid">
                                                                                <div class="row">
                                                                                    <div class="col-2">
                                                                                        <img src="assets/imgs/icons/pdf.png" alt="">
                                                                                    </div>
                                                                                    <div class="col-8">
                                                                                        <div class="file-name">
                                                                                            <div class="name"><?php echo $assignmentData["attachment"] ?></div>
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
                                                            <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                                <input type="file" class="d-none" id="file-selector-input" accept=".pdf">
                                                                <div class="col-12 px-1 mt-4">
                                                                    <div class="row" id="list-section">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span class="text-12 text-dark-red" id="file-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Plagiarism Report</label>
                                                            <select name="" class="form-select custom-select mt-2" disabled id="plagiarism-report">
                                                                <?php
                                                                $plagiarismStatus = Database::search("SELECT * FROM `plagiarism_status`");
                                                                while ($data = $plagiarismStatus->fetch_assoc()) {
                                                                ?>
                                                                    <option <?= $data['plagiarism_status_id'] == 2 ? 'selected' : '' ?> value="<?= $data['plagiarism_status_id'] ?>"><?= $data['plagiarism_status'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-12 text-dark-red" id="plagiarism-report-feedback"></span>
                                                        </div>

                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Assignment Status</label>
                                                            <select name="" class="form-select custom-select mt-2" id="assignment-status">
                                                                <?php
                                                                $assignmentStatus = Database::search("SELECT * FROM `assignment_status`");
                                                                while ($statusRow = $assignmentStatus->fetch_assoc()) {

                                                                    switch ($statusRow['assignment_status_id']) {
                                                                        case 1:
                                                                            if ($assignmentData['assignment_status_assignment_status_id'] == 1) {
                                                                ?>
                                                                                <option value="<?= $statusRow['assignment_status_id'] ?>" <?= $assignmentData['assignment_status_assignment_status_id'] == $statusRow['assignment_status_id'] ? 'selected' : '' ?>><?= $statusRow['assignment_status'] ?></option>
                                                                            <?php
                                                                            }
                                                                            break;
                                                                        case 2:
                                                                            if ($assignmentData['assignment_status_assignment_status_id'] != 1) {
                                                                            ?>
                                                                                <option value="<?= $statusRow['assignment_status_id'] ?>" <?= $assignmentData['assignment_status_assignment_status_id'] == $statusRow['assignment_status_id'] ? 'selected' : '' ?>><?= $statusRow['assignment_status'] ?></option>
                                                                            <?php
                                                                            }
                                                                            break;
                                                                        case 3:
                                                                            ?>
                                                                            <option value="<?= $statusRow['assignment_status_id'] ?>" <?= $assignmentData['assignment_status_assignment_status_id'] == $statusRow['assignment_status_id'] ? 'selected' : '' ?>><?= $statusRow['assignment_status'] ?></option>
                                                                            <?php
                                                                            break;
                                                                        case 4:
                                                                            if ($assignmentMarkReleased > 0) {
                                                                            ?>
                                                                                <option value="<?= $statusRow['assignment_status_id'] ?>" <?= $assignmentData['assignment_status_assignment_status_id'] == $statusRow['assignment_status_id'] ? 'selected' : '' ?>><?= $statusRow['assignment_status'] ?></option>
                                                                            <?php
                                                                            }
                                                                            break;
                                                                        case 5:
                                                                            if ($today > $endDateObj) {
                                                                            ?>
                                                                                <option value="<?= $statusRow['assignment_status_id'] ?>" <?= $assignmentData['assignment_status_assignment_status_id'] == $statusRow['assignment_status_id'] ? 'selected' : '' ?>><?= $statusRow['assignment_status'] ?></option>
                                                                <?php
                                                                            }
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-12 text-dark-red" id="assignment-status-feedback"></span>
                                                        </div>

                                                        <!-- ======================= LATE SUBMISSION (always visible) ======================= -->
                                                        <div class="col-12 col-lg-8 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Late Submission End Date Time</label>
                                                            <div class="col-12 mt-2">
                                                                <div class="row gx-3">
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="date" class="col-6 form-control custom-input" id="late-submision-date" onchange="acceptFutureDate('late-submision-date');" value="<?= $lateDateValue ?>">
                                                                        <span class="text-12 text-dark-red" id="late-submision-date-feedback"></span>
                                                                    </div>
                                                                    <div class="col-6 d-flex flex-column">
                                                                        <input type="time" class="col-6 form-control custom-input" id="late-submision-time" value="<?= $lateTimeValue ?>">
                                                                        <span class="text-12 text-dark-red" id="late-submision-time-feedback"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- ======================= RECORRECTION (always visible) ======================= -->
                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 sub-topic">Assignment Recorrection</label>
                                                            <div class="col-12 mt-3">
                                                                <div class="row gx-3">
                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                        <label for="" class="text-14 main-text mb-2">Recorrection Fee</label>
                                                                        <input type="number" min="0" step="0.01" class="col-6 form-control custom-input" id="recorrection-fee" value="<?= $recorrectionFeeValue ?>">
                                                                        <span class="text-12 text-dark-red" id="recorrection-fee-feedback"></span>
                                                                    </div>
                                                                    <div class="col-12 col-lg-8 d-flex flex-column">
                                                                        <label for="" class="text-14 main-text">Recorrection Apply End Date & Time</label>
                                                                        <div class="col-12 mt-2">
                                                                            <div class="row gx-3">
                                                                                <div class="col-6 d-flex flex-column">
                                                                                    <input type="date" class="col-6 form-control custom-input" id="recorrection-end-date" onchange="acceptFutureDate('recorrection-end-date');" value="<?= $recDateValue ?>">
                                                                                    <span class="text-12 text-dark-red" id="recorrection-end-date-feedback"></span>
                                                                                </div>
                                                                                <div class="col-6 d-flex flex-column">
                                                                                    <input type="time" class="col-6 form-control custom-input" id="recorrection-end-time" value="<?= $recTimeValue ?>">
                                                                                    <span class="text-12 text-dark-red" id="recorrection-end-time-feedback"></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="w-100 custom-btn" onclick="editAssignment(this,'<?php echo $id; ?>');">Save Changes</button>
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
                        <script>
                            const today = new Date().toISOString().split("T")[0];
                            document.getElementById('start-date').setAttribute("min", today);
                            document.getElementById('end-date').setAttribute("min", today);

                            const now = new Date();
                            const currentTime = now.toLocaleTimeString([], {
                                hour: '2-digit',
                                minute: '2-digit'
                            });

                            document.getElementById('start-time').setAttribute("min", currentTime);
                            document.getElementById('end-time').setAttribute("min", currentTime);
                        </script>

                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
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