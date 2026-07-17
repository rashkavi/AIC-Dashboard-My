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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lecture-recording-id']) && !empty($_GET['lecture-recording-id']))) {
            header("Location: ./lecture-schedule");
        } else {

            $id = $_GET['lecture-recording-id'];
            $timezone = new DateTimeZone("Asia/Colombo");
            $today = new DateTime("now", $timezone);
            $todayDate = $today->format('Y-m-d');
            $nowTime = $today->format('H:i:s');

            $lecture = Database::search("SELECT * FROM `lecture_recording`
                                        INNER JOIN `time_table` ON `lecture_recording`.`time_table_time_table_id` = `time_table`.`time_table_id`
                                        INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                        INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                                        INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                        INNER JOIN `lecture_type` ON `time_table`.`lecture_type_lecture_type_id` = `lecture_type`.`lecture_type_id`
                                        INNER JOIN `time_table_status` ON `time_table`.`time_table_status_time_table_status_id` = `time_table_status`.`time_table_status_id`
                                        WHERE `lecture_recording`.`lecture_recording_id`='" . $id . "'
                                        ");

            if ($lecture->num_rows == 0) {
                header("Location: ./lecture-schedule");
            } else {
                $lectureData = $lecture->fetch_assoc();

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 28))) {
                    header("Location: ./lecture-schedule");
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Edit Lecture Recording - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
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
                                    load_side_menu::load("Lecture Schedule");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("Lecture Schedule");
                                            ?>
                                        </div>
                                        <div class="container p-4 m-0 g-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-add-edit.php"; ?>
                                        </div>
                                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <span class="main-topic lh-lg" data-i18n="edit-lecture-recording">Edit Lecture Recording</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./lecture-schedule" data-i18n="lecture-schedule">Lecture Schedule</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="lecture-recording" href="./lecture-recording?lecture-id=<?= $lectureData['time_table_time_table_id'] ?>">Lecture Recording</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="edit-lecture-recording">edit Recording</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 p-0">
                                                            <div class="row p-0 m-0">
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

                                                                            <div class="details-row d-flex flex-column flex-lg-row">
                                                                                <div class="detail-item">
                                                                                    <div class="detail-icon">
                                                                                        <?php
                                                                                        if (!empty($lectureData["profile_img"]) && file_exists('./assets/imgs/users/' . $lectureData["profile_img"])) {
                                                                                        ?>
                                                                                            <img src="./assets/imgs/users/<?php echo $lectureData["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                        <?php
                                                                                        } else {
                                                                                            $firstNameInitial = strtoupper(substr($lectureData["user_fname"], 0, 1));
                                                                                            $lastNameInitial = strtoupper(substr($lectureData["user_lname"], 0, 1));
                                                                                        ?>
                                                                                            <div class="d-flex hwr-40 text-white align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </div>
                                                                                    <div class="detail-content">
                                                                                        <span class="detail-label">Lecturer</span>
                                                                                        <span class="detail-value"><?= $lectureData["title"] . '.' . $lectureData["user_fname"] . ' ' . $lectureData["user_lname"] ?></span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="detail-item">
                                                                                    <div class="detail-icon">
                                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                        </svg>
                                                                                    </div>
                                                                                    <div class="detail-content">
                                                                                        <span class="detail-label">Date Time</span>
                                                                                        <span class="detail-value"><?= $lectureData["date"] . ' ' . date("h:i A", strtotime($lectureData["start_time"])) . ' - ' . date("h:i A", strtotime($lectureData["end_time"])) ?? '--/--/----'; ?></span>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="detail-item">
                                                                                    <div class="detail-icon">
                                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                            <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                                            <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                                        </svg>
                                                                                    </div>
                                                                                    <div class="detail-content">
                                                                                        <span class="detail-label">Type</span>
                                                                                        <span class="detail-value"><?= $lectureData['lecture_type'] ?></span>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="detail-item">
                                                                                    <div class="detail-icon">
                                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                            <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z" />
                                                                                            <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z" />
                                                                                        </svg>
                                                                                    </div>
                                                                                    <div class="detail-content">
                                                                                        <span class="detail-label">Status</span>
                                                                                        <span class="detail-value">Completed</span>
                                                                                    </div>
                                                                                </div>

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 p-0 g-0 mt-4">
                                                            <div class="row d-flex flex-column gx-4 gy-3">
                                                                <div class="col-12 d-flex flex-column justify-content-start">
                                                                    <label class="text-14 main-text required" for="">Recording Link</label>
                                                                    <textarea class="form-control custom-text-area mt-2 br-0" id="recording-link">
                                                                        <?= htmlspecialchars($lectureData["lecture_recording"]) ?>
                                                                    </textarea>
                                                                    <span id="link-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Expire Date</label>
                                                                    <input type="date" class="form-control custom-input mt-2" id="expire-date" value="<?= $lectureData["expire_date"] ?? '' ?>">
                                                                    <span id="expire-date-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 col-md-6  d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Recording Thumbnail</label>
                                                                    <?php
                                                                    if (!empty($lectureData["recording_thumbnail"]) && file_exists('./assets/imgs/recording-thumbnail/' . $lectureData["recording_thumbnail"])) {
                                                                    ?>
                                                                        <img src="./assets/imgs/recording-thumbnail/<?= $lectureData["recording_thumbnail"] ?>" class="col-6 h-auto mt-2" id="recording-thumbnail" alt="recording thumbnail">
                                                                    <?php
                                                                    } else {

                                                                    ?>
                                                                        <img src="./assets/imgs/common/empty-thumbnail.webp" class="col-6 h-auto mt-2" id="recording-thumbnail" alt="recording thumbnail">
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                    <p class="col-6text-14 sub-text g-0 p-0 mt-3">Upload recording thumbnail here.
                                                                        <span class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</span>
                                                                        1200x800 pixels or 12:8 Ratio. Supported format: <span class="text-14 sub-topic g-0 p-0 m-0">.jpeg</span>
                                                                        <span class="text-14 sub-topic g-0 p-0 m-0">.jpg</span>
                                                                        <span class="text-14 sub-topic g-0 p-0 m-0">or</span>
                                                                        <span class="text-14 sub-topic g-0 p-0 m-0">.png</span>
                                                                    </p>
                                                                    <button class="col-12 col-md-6 col-xl-4 custom-btn d-flex align-items-center justify-content-center" onclick="uploadCover('file-input','recording-thumbnail');">
                                                                        Upload Image
                                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                    </button>

                                                                    <!-- Hiddedn file input -->
                                                                    <input type="file" id="file-input" class="d-none" accept="image/*" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 p-0 g-0 mt-4">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="custom-btn w-100" onclick="editLectureRecording(this,<?= $id ?>)">Save Recording</button>
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
        }
    }
}
?>