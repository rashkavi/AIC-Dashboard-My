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
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Attendace - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body class="overflow-hidden">
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("Attendance");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("Attendance");
                                ?>
                            </div>
                            <div class="container p-4 m-0" id="skeleton-list">
                                <?php
                                include "./includes/components/skeleton-header.php";
                                include "./includes/components/skeleton-courses.php"; ?>
                            </div>
                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row p-0 m-0 g-0">
                                            <span class="main-topic lh-lg">Attendance - Ongoing Lectures</span>
                                            <span class="text-14 sub-text lh-base">Track Your Attendance Records and Class Participation</span>
                                        </div>
                                    </div>

                                    <?php
                                    $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                                    $query = "
                                    SELECT 
                                        `tt`.`time_table_id`,
                                        `lt`.`lecture_type`,
                                        `lt`.`lecture_type_id`,
                                        `tts`.`time_table_status_id`,
                                        `tts`.`time_table_status`,
                                        `f`.`faculty`,
                                        `p`.`programme`,
                                        `i`.`intake`,
                                        `l`.`level`,
                                        `s`.`subject`,
                                        `s`.`subject_cover`,
                                        `tt`.`date`,
                                        `tt`.`start_time`,
                                        `tt`.`end_time`,
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
                                    WHERE `tt`.`lecture_type_lecture_type_id` != 3
                                    AND `tt`.`time_table_status_time_table_status_id` IN (1,5)
                                    AND `tt`.`date` = '" . $dateTime->format("Y-m-d") . "'
                                    AND `tt`.`start_time` < '" . $dateTime->format("H:i:s") . "'
                                    AND `tt`.`end_time` > '" . $dateTime->format("H:i:s") . "'
                                    ORDER BY `tt`.`time_table_id` DESC
                                ";

                                    $ongoingLectures = Database::search($query);
                                    if ($ongoingLectures->num_rows > 0) {
                                        while ($data = $ongoingLectures->fetch_assoc()) {

                                            $joinedStudents = Database::search("SELECT COUNT(*) as 'count' FROM `attendance` WHERE `time_table_time_table_id` = '" . $data['time_table_id'] . "'")->fetch_assoc()['count'];
                                    ?>
                                            <div class="col-12 px-4 py-3 component-theme mb-10">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="col-12 col-md-2 col-lg-1 d-flex align-items-center p-0 m-0 g-0">
                                                        <?php
                                                        $subject_cover = "./assets/imgs/subjects-covers/" . $data['subject_cover'];

                                                        if (!empty($data['subject_cover']) && file_exists($subject_cover)) {
                                                        ?>
                                                            <img src="<?= $subject_cover ?>" class="w-100 h-auto" alt="faculty img">
                                                        <?php
                                                        } else {
                                                        ?>
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="faculty img">
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                    <div class="col-12 col-md-10 col-lg-8 p-0  g-0 align-content-center">
                                                        <div class="row px-0 px-md-3 g-0 align-items-center">
                                                            <span class="text-15 text-dark-color fw-semibold lh-lg"><?= $data['faculty'] . "/" . $data['programme'] . "/" . $data['intake'] . "/" . $data['level'] . "/" . $data['subject'] ?></span>
                                                        </div>
                                                        <div class="d-flex px-0 px-md-3 g-0 align-items-center">
                                                            <div class="d-flex align-items-center">
                                                                <?php
                                                                if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                ?>
                                                                    <img src="./assets/imgs/users/<?php echo $data["profile_img"]; ?>" class="hwr-35" alt="User image">
                                                                <?php
                                                                } else {
                                                                    $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                    $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                ?>
                                                                    <div class="d-flex hwr-35 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                <?php
                                                                }
                                                                ?>
                                                                <span class="text-14 main-text lh-lg px-3">
                                                                    <?= htmlspecialchars($data['title'] . "." . $data['user_fname'] . " " . $data['user_lname']) ?>
                                                                </span>
                                                            </div>

                                                            <span class="text-14 main-text lh-lg px-3">|</span>
                                                            <span class="text-14 main-text lh-lg px-3">
                                                                <?= htmlspecialchars($data['lecture_type']) ?>
                                                            </span>
                                                            <span class="text-14 main-text lh-lg px-3">|</span>
                                                            <span class="text-14 main-text lh-lg px-3">
                                                                Joined Students: <?= (int)$joinedStudents ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-lg-3 p-0 mt-3 m-lg-0">
                                                        <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                            <div class="col-12 col-lg-6 mt-3 m-lg-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn w-100" onclick="window.location.href='ongoing-lecture-attendance?lecture-id=<?= $data['time_table_id']; ?>'">View</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                        }
                                    } else {
                                        ?>
                                         <div class="col-12 px-4 py-3 component-theme">
                                                    <div class="row align-items-center justify-content-center">
                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                    </div>
                                                </div>
                                    <?php
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

            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
        </body>

        </html>
<?php
    }
}
?>