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
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 2))) {
            header("Location: ./lecture-schedule");
        } else {
            $selectedDate = '';

            if (isset($_GET['date']) && !empty($_GET['date'])) {
                $selectedDate = $_GET['date'];
            }
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Schedule a Lecture - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include './includes/components/skeleton-header.php';
                                    include './includes/components/skeleton-add-edit.php'; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <span class="main-topic lh-lg" data-i18n="schedule-lecture-title">Schedule Lecture</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./lecture-schedule" data-i18n="lecture-schedule">Lecture Schedule</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="index" data-i18n="schedule-lecture">Schedule a Lecture</a></li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Faculty</label>
                                                            <select name="" id="faculty-selector" class="form-select custom-select mt-2" onchange="loadProgrammes();">
                                                                <option value="0" selected>- Select -</option>
                                                                <?php
                                                                $query = "SELECT * FROM `faculties`";
                                                                $faculties = Database::search($query);
                                                                while ($data = $faculties->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $data["faculty_id"]; ?>"><?php echo $data["faculty"]; ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Programmes</label>
                                                            <select name="" id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes();">
                                                                <option value="0" selected>- Select -</option>
                                                            </select>
                                                            <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Intake</label>
                                                            <select name="" id="intake-selector" disabled class="form-select custom-select mt-2" onchange="loadLevels();">
                                                                <option value="0" selected>- Select -</option>
                                                            </select>
                                                            <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Level</label>
                                                            <select name="" id="level-selector" disabled class="form-select custom-select mt-2" onchange="loadSubjects();">
                                                                <option value="0" selected>- Select -</option>
                                                            </select>
                                                            <span id="level-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Subject</label>
                                                            <select name="" id="subject-selector" disabled class="form-select custom-select mt-2" onchange="loadLecturers();">
                                                                <option value="0" selected>- Select -</option>
                                                            </select>
                                                            <span id="subject-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Lecturer</label>
                                                            <select name="" id="lecturer-selector" disabled class="form-select custom-select mt-2">
                                                                <option value="0" selected>- Select -</option>
                                                            </select>
                                                            <span id="lecturer-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 mt-3">
                                                            <label class="text-16 fw-semibold main-text mb-2 d-block required">
                                                                Lecture Schedule Type
                                                            </label>

                                                            <div class="d-flex gap-4 px-2 mt-4">
                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="oneTimeLecture" checked>
                                                                    <label class="form-check-label" for="oneTimeLecture">
                                                                        One-Time Lecture
                                                                    </label>
                                                                </div>

                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="recurringLecture">
                                                                    <label class="form-check-label" for="recurringLecture">
                                                                        Recurring Lecture
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="container mt-4 d-none" id="recurringLectureArea">
                                                            <div class="card shadow-none p-0 border-0">
                                                                <div class="card-body p-0">
                                                                    <!-- Date Range -->
                                                                    <div class="row g-3 mb-4">
                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                            <label class="text-16 main-text required" for="">Lecture Start Date</label>
                                                                            <input type="date" class="form-control custom-input mt-2" id="start-date">
                                                                            <span id="start-date-feedback" class="text-dark-red text-12"></span>
                                                                        </div>

                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                            <label class="text-16 main-text required" for="">Lecture End Date</label>
                                                                            <input type="date" class="form-control custom-input mt-2" id="end-date">
                                                                            <span id="end-date-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                            <label class="text-16 main-text required" for="">Lecture Start Time</label>
                                                                            <input type="time" class="form-control custom-input mt-2" id="r-start-time">
                                                                            <span id="r-start-time-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                            <label class="text-16 main-text required" for="">Lecture End Time</label>
                                                                            <input type="time" class="form-control custom-input mt-2" id="r-end-time">
                                                                            <span id="r-end-time-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                    </div>
                                                                    <!-- Recurring Days -->
                                                                    <div class="mb-4">
                                                                        <label class="form-label fw-medium required">Recurring Days</label>
                                                                        <div class="d-flex flex-wrap gap-2 mt-1">
                                                                            <?php
                                                                            $data = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

                                                                            for ($i = 0; $i < count($data); $i++) {
                                                                            ?>
                                                                                <div class="chip align-content-center" data-day-id="<?= $i + 1 ?>" onclick="dateChipBoxAction(this);">
                                                                                    <span class="chip-label"><?php echo $data[$i]; ?></span>
                                                                                    <span class="chip-check-icon">
                                                                                        <iconify-icon icon="mdi:check"></iconify-icon>
                                                                                    </span>
                                                                                </div>
                                                                            <?php
                                                                            }
                                                                            ?>

                                                                        </div>
                                                                        <span id="days-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <!-- Skip Options -->
                                                                    <div class="mb-3">
                                                                        <label class="form-label fw-medium">Skip Lectures On</label>
                                                                        <div class="mt-2">
                                                                            <div class="form-check mb-2">
                                                                                <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" id="skip-poya">
                                                                                <label class="form-check-label" for="skip-poya">
                                                                                    Skip Poya Days (Sri Lanka public holidays)
                                                                                </label>
                                                                            </div>
                                                                            <div class="form-check mb-2">
                                                                                <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" id="skip-holidays">
                                                                                <label class="form-check-label" for="skip-holidays">
                                                                                    Skip Special / Public Holidays
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Custom Skip Dates -->
                                                                    <div class="mt-3 d-none" id="custom-skip-dates">
                                                                        <label class="form-label fw-medium">Select Custom Skip Dates</label>
                                                                        <input type="date" class="form-control mb-2">
                                                                        <input type="date" class="form-control">
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>


                                                    </div>
                                                </div>
                                                <div class="col-12 p-0 g-0 mt-4" id="onetimeLectureArea">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Date</label>
                                                            <input type="date" class="form-control custom-input mt-2" id="lecture-date" value="<?= $selectedDate ?>">
                                                            <span id="date-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Pick Start Time</label>
                                                            <input type="time" class="form-control custom-input mt-2" id="start-time">
                                                            <span id="start-time-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Pick End Time</label>
                                                            <input type="time" class="form-control custom-input mt-2" id="end-time">
                                                            <span id="end-time-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-6 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Lecture Title</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="title">
                                                            <span id="title-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-16 main-text" for="">Lecture Attachment</label>
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
                                                            <div id="attachment-feedback" class="text-dark-red text-12"></div>
                                                            <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 g-0 mt-3" id="lectureTypeArea">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 d-flex flex-column mb-4">
                                                            <label class="text-16 main-text required" for="">Lecture Type</label>
                                                            <div class="d-flex gap-4 px-2 mt-4">

                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="physical-check-box" checked>
                                                                    <label class="text-16 sub-text mx-2">Physical</label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="online-check-box">
                                                                    <label class="text-16 sub-text mx-2">Online</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column mb-4 d-none" id="lecture-link-area">
                                                            <label class="text-16 main-text" for="">Lecture Link</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="link-input">
                                                            <span id="link-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-12 d-flex flex-column align-items-end">
                                                            <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                <button class="custom-btn w-100" onclick="scheduleLecture(this);">Save Lecture</button>
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
                <script src="./assets/js/fileChooser.js"></script>
                <script>
                    const oneTime = document.getElementById("oneTimeLecture");
                    const recurring = document.getElementById("recurringLecture");
                    const recurringSection = document.getElementById("recurring-section");

                    oneTime.addEventListener("change", () => {
                        recurringSection.classList.add("d-none");
                    });

                    recurring.addEventListener("change", () => {
                        recurringSection.classList.remove("d-none");
                    });
                </script>

            </body>

            </html>
<?php
        }
    }
}
?>