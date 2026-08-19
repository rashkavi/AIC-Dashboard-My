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

            $lecture_id = $_GET['lecture-id'];
            $subject_id = 0;

            $query = "
            SELECT 
                `tt`.`time_table_id`,
                `lt`.`lecture_type`,
                `lt`.`lecture_type_id`,
                `tts`.`time_table_status_id`,
                `tts`.`time_table_status`,
                `f`.`faculty`,
                `p`.`programme`,
                `p`.`programme_id`,
                `p`.`programme_cover`,
                `i`.`intake_id`,
                `i`.`intake`,
                `l`.`level`,
                `l`.`level_id`,
                `s`.`subject_id`,
                `s`.`subject`,
                `s`.`subject_cover`,
                `s`.`subject_type_subject_type_id`,
                `tt`.`date`,
                `tt`.`start_time`,
                `tt`.`end_time`,
                `tt`.`lecture_title`,
                `u`.`user_fname`,
                `u`.`user_lname`,
                `u`.`profile_img`,
                `t`.`title`
            FROM `time_table` AS `tt`
            INNER JOIN `lecture_type` AS `lt`
                ON `tt`.`lecture_type_lecture_type_id` = `lt`.`lecture_type_id`
            INNER JOIN `time_table_status` AS `tts`
                ON `tt`.`time_table_status_time_table_status_id` = `tts`.`time_table_status_id`  
            INNER JOIN `lecturers` AS `lec`
                ON `tt`.`lecturers_lecturers_id` = `lec`.`lecturers_id`
            INNER JOIN `users` AS `u`
                ON `lec`.`users_user_id` = `u`.`user_id`
            INNER JOIN `title` AS `t`
                ON `u`.`title_title_id` = `t`.`title_id`
            INNER JOIN `subjects` AS `s`
                ON `lec`.`subjects_subject_id` = `s`.`subject_id`
            INNER JOIN `levels` AS `l`
                ON `s`.`levels_level_id` = `l`.`level_id`
            INNER JOIN `intakes` AS `i`
                ON `l`.`intakes_intake_id` = `i`.`intake_id`
               
            INNER JOIN `programmes` AS `p`
                ON `i`.`programmes_programme_id` = `p`.`programme_id`
          
            INNER JOIN `faculties` AS `f`
                ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
            WHERE 
             `tt`.`time_table_status_time_table_status_id` = 1
            AND `tt`.`time_table_id` = '" . $lecture_id . "'
                        
        ";
            if (isset($_GET['subject-id']) && !empty($_GET['subject-id'])) {
                $subject_id = $_GET['subject-id'];
                $query .= " AND `s`.`subject_id`='" . $subject_id . "'";
            }

            $lecture = Database::search($query);

            if ($lecture->num_rows == 0) {
                header("Location: ./lecture-schedule");
            } else {
                $lectureData = $lecture->fetch_assoc();
                $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));
                $isValidLecture = true;
                if ($lectureData['lecture_type_id'] != 3) {
                    $lectureDateTime = new DateTime($lectureData['date'] . " " . $lectureData['start_time'], new DateTimeZone("Asia/Colombo"));
                    $lectureEndDateTime = new DateTime($lectureData['date'] . " " . $lectureData['end_time'], new DateTimeZone("Asia/Colombo"));
                    if ($dateTime < $lectureDateTime) {
                        $isValidLecture = false;
                    }
                }

                if (!$isValidLecture) {
                    header("Location: ./lecture-schedule");
                } else {

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || ($subject_id == 0 ? UserPermissions::validPermisisonType('course_overview') : UserPermissions::validPermisisonType('lecture_schedule')))) {
                        header("Location: ./dashboard");
                    } else {

                        $timezone = new DateTimeZone("Asia/Colombo");
                        $today = new DateTime("now", $timezone);
                        $todayDate = $today->format('Y-m-d');
                        $nowTime = $today->format('H:i:s');

                        $isHasRecording = Database::search("SELECT * FROM `lecture_recording_has_lectures`
                        INNER JOIN `lecture_recording` ON `lecture_recording_has_lectures`.`lecture_recording_lecture_recording_id` = `lecture_recording`.`lecture_recording_id`
                        INNER JOIN `users` ON `lecture_recording`.`users_user_id` = `users`.`user_id`
                        INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id` 
                        WHERE `time_table_time_table_id`='" . $lecture_id . "'");

?>
                        <!DOCTYPE html>
                        <html lang="en">

                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <meta name="robots" content="noindex, nofollow">
                            <title>Lecture Recording - Admin Dashboard | <?= Project::getBaseName() ?></title>

                            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                        </head>

                        <body class="overflow-hidden" onload="initializeScheduleLecture();">
                            <div class="notifications"></div>
                            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                                <div id="appContainer" class="row p-0">
                                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0  vh-100">
                                        <?php
                                        include "includes/side-menu.php";
                                        if ($subject_id == 0) {
                                            load_side_menu::load("Lecture Schedule");
                                        } else {
                                            load_side_menu::load("Course Overview");
                                        }
                                        ?>
                                    </div>
                                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 p-0 m-0 g-0">
                                                <?php
                                                include "./includes/header.php";
                                                if ($subject_id == 0) {
                                                    selectItem::setSelected("Lecture Schedule");
                                                } else {
                                                    selectItem::setSelected("Course Overview");
                                                }
                                                ?>
                                            </div>
                                            <div class="container p-4 m-0 g-0" id="skeleton-list">
                                                <?php
                                                include "./includes/components/skeleton-header.php";
                                                include "./includes/components/skeleton-dashboards.php"; ?>
                                            </div>
                                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                                <div class="row p-4 m-0 g-0">
                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row p-0 m-0 g-0">
                                                            <div class="col-12 col-lg-10">
                                                                <div class="row">
                                                                    <span class="main-topic lh-lg" data-i18n="lecture-recording">Lecture Recording</span>
                                                                    <?php
                                                                    if ($subject_id == 0) {
                                                                    ?>
                                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                            <ol class="breadcrumb m-0">
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./lecture-schedule" data-i18n="lecture-schedule">Lecture Schedule</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="lecture-recording">Lecture Recordding</a></li>
                                                                            </ol>
                                                                        </nav>
                                                                    <?php
                                                                    } else {
                                                                    ?>
                                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                            <ol class="breadcrumb m-0">
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programme-id=<?= $lectureData['programme_id'] ?>"><?= $lectureData['programme'] ?></a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./level-details?level-id=<?= $lectureData['level_id'] ?>"><?= $lectureData['level'] ?></a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-details?subject-id=<?= $lectureData['subject_id'] ?>"><?= $lectureData['subject'] ?></a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subject-lecture-schedule?subject-id=<?= $lectureData['subject_id'] ?>">Lectures</a></li>
                                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Lecture Attendance</a></li>
                                                                            </ol>
                                                                        </nav>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                            <?php
                                                            if ($isHasRecording->num_rows > 0) {
                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || ($subject_id != 0 ? UserPermissions::validPermisisonType('course_overview', 0) : UserPermissions::validPermisisonType('lecture_schedule', 0))) {
                                                            ?>
                                                                    <div class="col-12 col-lg-2">
                                                                        <div class="row align-items-center">
                                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                <?php
                                                                                if ($subject_id == 0) {
                                                                                ?>
                                                                                    <button class="custom-btn w-100" onclick="window.location = 'upload-lecture-recording?lecture-id=<?= $lecture_id ?>'">Upload Recording</button>
                                                                                <?php
                                                                                } else {
                                                                                ?>
                                                                                    <button
                                                                                        class="custom-btn w-100"

                                                                                        onclick="window.location = 'upload-lecture-recording?lecture-id=<?= $lecture_id ?>&subject-id=<?= $subject_id ?>'">
                                                                                        Upload Recording
                                                                                    </button>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                            <?php
                                                                }
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    if ($today < $lectureEndDateTime) {
                                                    ?>
                                                        <div class="col-12 px-4 py-3 component-theme">
                                                            <div class="row align-items-center justify-content-center">
                                                                <img src="./assets/imgs/common/emty-video.png" class="no-data-icon" alt="No data" />
                                                            </div>
                                                        </div>
                                                        <?php
                                                    } else {

                                                        if ($isHasRecording->num_rows == 0) {
                                                        ?>
                                                            <div class="col-12 px-4 py-3 component-theme">
                                                                <div class="d-flex flex-column align-items-center justify-content-center">
                                                                    <img src="./assets/imgs/common/emty-video.png" class="no-data-icon" alt="No data" />
                                                                    <?php
                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || ($subject_id != 0 ? UserPermissions::validPermisisonType('course_overview', 0) : UserPermissions::validPermisisonType('lecture_schedule', 0))) {
                                                                        if ($subject_id == 0) {
                                                                    ?>
                                                                            <button class="custom-btn px-5" data-i18n="upload-lecture-recording" onclick="window.location = 'upload-lecture-recording?lecture-id=<?= $lecture_id ?>'">Upload Recording</button>
                                                                        <?php
                                                                        } else {
                                                                        ?>
                                                                            <button
                                                                                class="custom-btn px-5"
                                                                                data-i18n="upload-lecture-recording"
                                                                                onclick="window.location = 'upload-lecture-recording?lecture-id=<?= $lecture_id ?>&subject-id=<?= $subject_id ?>'">
                                                                                Upload Recording
                                                                            </button>
                                                                    <?php
                                                                        }
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        } else {
                                                        ?>
                                                            <div class="col-12">
                                                                <div class="row gx-4 gy-4">
                                                                    <?php
                                                                    $partsEnabled = $isHasRecording->num_rows > 1 ? true : false;
                                                                    for ($i = 0; $i < $isHasRecording->num_rows; $i++) {
                                                                        $lectureRecordingData = $isHasRecording->fetch_assoc();
                                                                    ?>
                                                                        <div class="col-12 col-lg-4">
                                                                            <div class="student-details-card position-relative px-4 py-3 component-theme">
                                                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                                                    <div class="col-12 d-flex flex-column align-items-center p-0 m-0 g-0">
                                                                                        <div class="lecture-thumbnail"
                                                                                            onclick='openLectureRecording(<?= json_encode($lectureRecordingData["lecture_recording"]) ?>);'>
                                                                                            <?php
                                                                                            if (!empty($lectureRecordingData['recording_thumbnail']) && file_exists('./assets/imgs/recording-thumbnail/' . $lectureRecordingData['recording_thumbnail'])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/recording-thumbnail/<?= $lectureRecordingData['recording_thumbnail'] ?>" alt="Lecture Thumbnail" class="thumbnail-img">
                                                                                            <?php
                                                                                            } else if (!empty($lectureData['programme_cover']) && file_exists('./assets/imgs/programmes-covers/' . $lectureData['programme_cover'])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/programmes-covers/<?= $lectureData['programme_cover'] ?>" alt="Lecture Thumbnail" class="thumbnail-img">
                                                                                            <?php
                                                                                            } else {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/common/empty-thumbnail.webp" alt="Lecture Thumbnail" class="thumbnail-img">
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                          
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-12 d-flex flex-column px-0 mt-3 gap-3 align-content-center justify-content-center">
                                                                                        <h6 class="text-18 text-dark-color fw-semibold m-0"><?= $lectureData["lecture_title"]; ?> - <?= $partsEnabled ? "Part " . ($i + 1) : "" ?></h6>
                                                                                        <div class="d-flex flex-column gap-3 mt-3">
                                                                                            <div class="student-details-item d-flex align-items-center gap-2">
                                                                                                <iconify-icon icon="hugeicons:user-ai" width="20" height="20" class="dark-theme"></iconify-icon>
                                                                                                <span class="student-details-intake text-13 sub-text">Uploaded By: <?= $lectureRecordingData['title'] . " " . $lectureRecordingData['user_fname'] . " " . $lectureRecordingData['user_lname'] ?>
                                                                                                </span>
                                                                                            </div>
                                                                                            <div class="student-details-item d-flex align-items-center gap-2">
                                                                                                <iconify-icon icon="hugeicons:date-time" width="20" height="20" class="dark-theme"></iconify-icon>
                                                                                                <span class="student-details-intake text-13 sub-text">Uploaded Date: <?= date('Y-m-d H:i A', strtotime($lectureRecordingData['added_at'])) ?>
                                                                                                </span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                    <?php
                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || ($subject_id != 0 ? UserPermissions::validPermisisonType('course_overview', 0) : UserPermissions::validPermisisonType('lecture_schedule', 0))) {
                                                                                    ?>
                                                                                        <div class="col-12 p-0 mt-3 m-lg-0">
                                                                                            <div class="row gx-3 mt-3 h-100 align-content-center justify-content-end">
                                                                                                <div class="col-6 d-flex justify-content-end align-items-center">
                                                                                                    <button class="custom-danger-btn w-100" onclick="window.location = 'edit-lecture-recording?lecture-recording-id=<?= $lectureRecordingData['lecture_recording_id'] ?>' ">Edit</button>
                                                                                                </div>
                                                                                                <div class="col-6 d-flex justify-content-end align-items-center">
                                                                                                    <button class="custom-danger-btn w-100" onclick="openRemoeRecordigConfirmationModal(<?= $lectureRecordingData['lecture_recording_id'] ?>)">Remove</button>
                                                                                                </div>

                                                                                            </div>
                                                                                        </div>
                                                                                    <?php
                                                                                    }
                                                                                    ?>

                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                    <?php
                                                        }
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

                            <div class="modal fade" id="removeLectureRecordingConfirmModal" tabindex="-1"
                                aria-labelledby="removeLectureRecordingConfirmModalTitle" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow">

                                        <div class="d-flex justify-content-end pe-3 pt-3 position-relative">
                                            <button type="button"
                                                class="btn-close text-end"
                                                data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>

                                        <div class="modal-body text-center p-4">

                                            <div class="mb-3">
                                                <iconify-icon
                                                    icon="bx:error-circle"
                                                    width="48"
                                                    height="48"
                                                    class="text-danger">
                                                </iconify-icon>
                                            </div>

                                            <h5 class="fw-semibold mb-2" id="removeLectureRecordingConfirmModalTitle">
                                                Confirm Lecture Recording Removal
                                            </h5>

                                            <p class="text-muted mb-3">
                                                You are about to remove this <strong>Lecture Recording</strong>.
                                            </p>

                                            <div class="alert alert-warning text-start small mb-4">
                                                <div class="d-flex align-items-start gap-2">
                                                    <iconify-icon icon="mdi:alert-outline" width="20"></iconify-icon>
                                                    <div>
                                                        <strong>This action will:</strong>
                                                        <ul class="mb-0 ps-3">
                                                            <li>Permanently remove the selected lecture recording</li>
                                                            <li>Make the recording unavailable to students and staff</li>
                                                            <li>This action <strong>cannot be undone</strong></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-center gap-3">
                                                <button type="button"
                                                    class="btn btn-outline-secondary px-4 br-0"
                                                    data-bs-dismiss="modal">
                                                    Cancel
                                                </button>

                                                <button type="button"
                                                    id="confirmRemoveLectureRecording"
                                                    class="btn btn-danger px-4 br-0">
                                                    <iconify-icon icon="mdi:delete-outline" class="me-1"></iconify-icon>
                                                    Remove Recording
                                                </button>
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
}
?>