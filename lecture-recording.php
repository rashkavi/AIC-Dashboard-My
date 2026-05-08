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

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("super_lecture_schedule", 28))) {
                        header("Location: ./lecture-schedule");
                    } else {

                        $timezone = new DateTimeZone("Asia/Colombo");
                        $today = new DateTime("now", $timezone);
                        $todayDate = $today->format('Y-m-d');
                        $nowTime = $today->format('H:i:s');

                        $isHasRecording = Database::search("SELECT `lecture_recording`.*,`users`.`user_fname`,`users`.`user_lname`,`users`.`profile_img`,`title`.`title` FROM `lecture_recording`
                        INNER JOIN `users` ON `users`.`user_id` = `lecture_recording`.`users_user_id`
                        INNER JOIN `title` ON `title`.`title_id` = `users`.`title_title_id` 
                        WHERE `time_table_time_table_id` = '" . $lecture_id . "'");
                        if ($isHasRecording->num_rows > 0) {
                            $lectureRecordingData = $isHasRecording->fetch_assoc();
                        }
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
                                                            ?>
                                                                <div class="col-12 col-lg-2">
                                                                    <div class="row align-items-center">
                                                                        <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                            <button class="custom-btn px-4" data-i18n="edit-lecture-recording" onclick="window.location = 'edit-lecture-recording?lecture-recording-id=<?= $lectureRecordingData['lecture_recording_id'] ?>' ">Edit Lecture Recording</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php
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
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        } else {
                                                            $iframeHtml = $lectureRecordingData['lecture_recording'];
                                                            $src = "";
                                                            if (preg_match('/src="([^"]+)"/', $iframeHtml, $matches)) {
                                                                $src = $matches[1];
                                                            }
                                                        ?>
                                                            <div class="col-12 component-theme p-4">
                                                                <div class="row p-0">
                                                                    <div class="col-12 col-lg-6">
                                                                        <div class="lecture-thumbnail" onclick="openLectureModal('<?= $src ?>')">
                                                                            <?php
                                                                            if (!empty($lectureRecordingData['recording_thumbnail']) && !file_exists('./assets/imgs/recording-thumbnail/' . $lectureRecordingData['recording_thumbnail'])) {
                                                                            ?>
                                                                                <img src="./assets/imgs/common/empty-thumbnail.webp" alt="Lecture Thumbnail" class="thumbnail-img">
                                                                            <?php
                                                                            } else {
                                                                            ?>
                                                                                <img src="./assets/imgs/recording-thumbnail/<?= $lectureRecordingData['recording_thumbnail'] ?>" alt="Lecture Thumbnail" class="thumbnail-img">
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                            <div class="recording-pulse"></div>
                                                                            <div class="play-icon">
                                                                                <iconify-icon icon="line-md:play-filled" width="60" height="60" class="dark-theme"></iconify-icon>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12 col-lg-6">
                                                                        <div class="row p-0 m-0 justify-content-end">
                                                                            <h3 class="sub-topic text-16 fw-bold mb-3 p-0">Recorrding Details</h3>
                                                                            <div class="payment-card-container p-0">
                                                                                <div class="payment-card shadow-none">
                                                                                    <!-- Background elements -->
                                                                                    <div class="card-background"></div>
                                                                                    <!-- Card content -->
                                                                                    <div class="card-content">
                                                                                        <div class="payment-header">
                                                                                            <!-- Reference on left -->
                                                                                            <div class="reference-section">
                                                                                                <span class="reference-label">Title</span>
                                                                                                <h2 class="reference-number"><?= $lectureData["lecture_title"]; ?></h2>
                                                                                                <hr>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="details-row d-flex flex-column">
                                                                                            <div class="detail-item">
                                                                                                <div class="detail-icon">
                                                                                                    <?php
                                                                                                    if (!empty($lectureRecordingData["profile_img"]) && file_exists('./assets/imgs/users/' . $lectureRecordingData["profile_img"])) {
                                                                                                    ?>
                                                                                                        <img src="./assets/imgs/users/<?php echo $lectureRecordingData["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                                    <?php
                                                                                                    } else {
                                                                                                        $firstNameInitial = strtoupper(substr($lectureRecordingData["user_fname"], 0, 1));
                                                                                                        $lastNameInitial = strtoupper(substr($lectureRecordingData["user_lname"], 0, 1));
                                                                                                    ?>
                                                                                                        <div class="d-flex hwr-40 text-white align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                </div>
                                                                                                <div class="detail-content">
                                                                                                    <span class="detail-label">Added By</span>
                                                                                                    <span class="detail-value"><?= $lectureRecordingData["title"] . '.' . $lectureRecordingData["user_fname"] . ' ' . $lectureRecordingData["user_lname"] ?></span>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div class="detail-item">
                                                                                                <div class="detail-icon">
                                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                                    </svg>
                                                                                                </div>
                                                                                                <div class="detail-content">
                                                                                                    <span class="detail-label">Expire Date</span>
                                                                                                    <span class="detail-value"><?= $lectureRecordingData["expire_date"] ?? '--/--/----'; ?></span>
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

                            <div class="modal fade br-0" id="lectureView" aria-hidden="true" aria-labelledby="lectureViewLabel" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                    <div class="modal-content p-4">
                                        <div class="modal-header p-0 border-0">
                                            <h1 class="modal-title text-16 text-dark-color" id="lectureViewLabel">Lecture</h1>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body align-content-center justify-content-center p-0 mt-3" id="lectureModalBody">
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