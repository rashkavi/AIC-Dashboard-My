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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lecture-id']) && !empty($_GET['lecture-id']))) {
            header("Location: ./lecture-schedule");
        } else {

            $id = $_GET['lecture-id'];
            $lecture = Database::search("SELECT * FROM `time_table`
                                        INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                        INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                                        INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                        INNER JOIN `subjects` ON `lecturers`.`subjects_subject_id` = `subjects`.`subject_id`
                                        INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                        INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                        INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                        INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                                        WHERE `time_table`.`time_table_id`='" . $id . "'
                                        ");

            if ($lecture->num_rows == 0) {
                header("Location: ./lecture-schedule");
            } else {
                $lectureData = $lecture->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('pre_Recorded_lectures', 10, $lectureData['subject_id']))) {
                    header("Location: ./subject-pre-recorded-lectures?subejct-id=" . $lectureData['subejcts_subject_id']);
                } else {

                    $timezone = new DateTimeZone("Asia/Colombo");
                    $today = new DateTime("now", $timezone);
                    $todayDate = $today->format('Y-m-d');
                    $nowTime = $today->format('H:i:s');
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Schedule Lecture - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                                    <div class="row p-0 m-0 g-0">
                                                        <span class="main-topic lh-lg">Pre Recorded Lecture Details</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programme-id=<?= $lectureData['programme_id'] ?>"><?= $lectureData['programme'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subjects?level-id=<?= $lectureData['level_id'] ?>"><?= $lectureData['level'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $lectureData['subject_id'] ?>"><?= $lectureData['subject'] ?></a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-pre-recorded-lectures?subejct-id=<?= $lectureData['subject_id'] ?>">Pre Recorded Lectures</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Pre Recorded Lecture Details</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>

                                                <div class="col-12 component-theme p-4 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Select Lecturer</label>
                                                            <select name="" id="lecturer-selector" class="form-select custom-select mt-2">
                                                                <option value="<?php echo $lectureData["lecturers_lecturers_id"]; ?>" selected><?php echo $lectureData["title"] . "." . $lectureData["user_fname"] . " " . $lectureData["user_lname"]; ?></option>
                                                                <?php
                                                                $subjectLeccturers = Database::search("
                                                                    SELECT `lecturers`.`lecturers_id`,`title`.`title`,`user_fname`,' ',`user_lname` FROM `lecturers`
                                                                    INNER JOIN `users`
                                                                    ON `lecturers`.`users_user_id`=`users`.`user_id`
                                                                    INNER JOIN `title`
                                                                    ON `users`.`title_title_id`=`title`.`title_id`
                                                                    WHERE `lecturers`.`status_status_id`='1' AND `users`.`status_status_id`='1' AND `lecturers`.`subjects_subject_id`='" . $lectureData['subjects_subject_id'] . "' AND `lecturers`.`lecturers_id`!='" . $lectureData["lecturers_lecturers_id"] . "'");

                                                                if ($subjectLeccturers->num_rows > 0) {
                                                                    while ($lecturer = $subjectLeccturers->fetch_assoc()) {
                                                                ?>
                                                                        <option value="<?= $lecturer['lecturers_id'] ?>" selected><?= $lecturer['title'] . ' . ' . $lecturer['user_fname'] . ' ' . $lecturer['user_lname'] ?></option>
                                                                <?php
                                                                    }
                                                                }
                                                                ?>

                                                            </select>
                                                            <span id="lecturer-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Duration</label>
                                                            <input type="number" class="form-control custom-input mt-2" value="<?php echo $lectureData["duration"]; ?>" id="duration">
                                                            <span id="duration-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Recording Status</label>
                                                            <select name="" id="status-selector" class="form-select custom-select mt-2">
                                                                <?php
                                                                if ($lectureData["time_table_status_time_table_status_id"] != 1) {
                                                                ?>
                                                                    <option value="1">Unlocked</option>
                                                                    <option value="2" selected>Locked</option>
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <option value="1" selected>Unlocked</option>
                                                                    <option value="2">Locked</option>
                                                                <?php
                                                                }

                                                                ?>

                                                            </select>
                                                            <span id="lecturer-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column" id="lecture-link-darea">
                                                            <label class="text-14 main-text" for="">Lecture Link</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="link-input" value="<?php echo htmlspecialchars($lectureData['lecture'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <span id="link-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Lecture Attachment</label>
                                                            <div class="col-12 px-1 mt-2">
                                                                <div class="row">
                                                                    <?php
                                                                    $attachment = Database::search("SELECT * FROM `attachments` WHERE `time_table_time_table_id` = '" . $id . "'");
                                                                    if ($attachment->num_rows > 0) {
                                                                        while ($attachmentData = $attachment->fetch_assoc()) {
                                                                            if (!empty($attachmentData["attachment"]) && file_exists("./assets/uploads/lectures-attachments/" . $attachmentData["attachment"])) {
                                                                    ?>
                                                                                <div class="col-12 col-lg-4 file-item cursor-pointer" data-attachment-id="<?= $attachmentData['attachment_id'] ?>">
                                                                                    <div class="bl-solid">
                                                                                        <div class="row">
                                                                                            <div class="col-10" onclick="viewLectureAttachment('<?php echo $attachmentData['attachment'] ?>', '<?php echo $attachmentData['attachment_name'] ?>')">
                                                                                                <div class="row p-3">
                                                                                                    <div class="col-3">
                                                                                                        <img src="assets/imgs/icons/pdf.png" alt="">
                                                                                                    </div>
                                                                                                    <div class="col-8">
                                                                                                        <div class="file-name">
                                                                                                            <div class="name"><?php echo $attachmentData["attachment_name"] ?></div>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>

                                                                                            <div class="col-2">
                                                                                                <!-- Container -->
                                                                                                <div class="d-flex align-items-center justify-content-center p-0 m-0 table-btn-danger h-100 w-100"
                                                                                                    onclick="removeLectureAttachment(<?= $attachmentData['attachment_id'] ?>)">
                                                                                                    <iconify-icon icon="pajamas:remove-all" width="20" height="20"
                                                                                                        class="text-danger p-0 m-0 text-center">
                                                                                                    </iconify-icon>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                    <?php
                                                                            }
                                                                        }
                                                                    }

                                                                    ?>
                                                                </div>
                                                            </div>
                                                            <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                                <input type="file" class="d-none" id="file-selector-input" multiple accept=".pdf">
                                                                <div class="col-12 px-1 mt-4">
                                                                    <div class="row" id="list-section">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span id="attachment-feedback" class="text-dark-red text-12"></span>
                                                            <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                                        </div>
                                                        <div class="col-12 col-md-12 d-flex flex-column align-items-end">
                                                            <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                <button class="custom-btn w-100" onclick="editSubjectPreRecordedLecture(this,<?php echo $id ?>);">Save Changes</button>
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

            <?php
                }
            }
            ?>
<?php
        }
    }
}
?>