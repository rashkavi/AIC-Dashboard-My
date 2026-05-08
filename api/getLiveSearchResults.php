<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {

        $text = $_GET['text'];

        if (empty($text)) {
            $status->emptyText = 'Please enter a search term';
        } else {

            $usersQuery = "SELECT `user_id`, `user_fname`, `user_lname`,`profile_img`, `user_email`, `user_mobile`, `title` FROM `users` 
            INNER JOIN `title` AS `t` ON `users`.`title_title_id` = `t`.`title_id`
             WHERE (`user_fname` LIKE '" . $text . "%' OR `user_lname` LIKE '" . $text . "%' OR `user_email` LIKE '" . $text . "%' OR `user_mobile` LIKE '" . $text . "%') ";

            $studentsQuery = "SELECT `student_id`,`title`, `first_name`, `last_name`,`profile_img`,`email`,`whatsapp` FROM `students` 
            INNER JOIN `login_details` AS `ld` ON `students`.`login_details_login_details_id` = `ld`.`login_details_id`
            INNER JOIN `title` AS `t` ON `students`.`title_title_id` = `t`.`title_id`
            WHERE (`first_name` LIKE '" . $text . "%' OR `last_name` LIKE '" . $text . "%' OR `nic` LIKE '" . $text . "%' OR `email` LIKE '" . $text . "%') ";

            $intakeStudentsQuery = "SELECT `title`, `first_name`, `last_name`, `students_has_intakes`.`student_id`,`students_has_intakes`.`students_has_intakes_id`,`profile_img` FROM `students_has_intakes` 
                INNER JOIN `students` ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
                INNER JOIN `login_details` AS `ld` ON `students`.`login_details_login_details_id` = `ld`.`login_details_id`
                INNER JOIN `title` AS `t` ON `students`.`title_title_id` = `t`.`title_id`
                WHERE (`first_name` LIKE '" . $text . "%' OR `last_name` LIKE '" . $text . "%' OR `nic` LIKE '" . $text . "%' 
                OR `email` LIKE '" . $text . "%' OR `students_has_intakes`.`student_id` LIKE '" . $text . "%') ";

            $facultiesQuery = "SELECT `faculty_id`, `faculty`,`faculty_cover`,COUNT(`programme_id`) AS `programme_count` FROM `faculties` 
            LEFT JOIN `programmes` ON `faculties`.`faculty_id` = `programmes`.`faculties_faculty_id`
                WHERE `faculty` LIKE '%" . $text . "%' GROUP BY `faculties`.`faculty_id`";

            $programmesQuery = "SELECT `programme_id`, `programme`, `faculty`, `programme_cover` FROM `programmes` 
                INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                WHERE `programme` LIKE '%" . $text . "%'";

            $intakesQuery = "SELECT `intake_id`, `intake`, `programme`, `faculty`,`intake_cover` FROM `intakes` 
                INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                WHERE `intake` LIKE '%" . $text . "%'";

            $levelsQuery = "SELECT `level_id`, `level`, `intake`, `programme`, `faculty`,`level_cover` FROM `levels` 
                INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                WHERE `level` LIKE '%" . $text . "%'";

            $subjectQuery = "SELECT `subject_id`, `subject`, `level`, `intake`, `programme`, `faculty`,`subject_cover` FROM `subjects`
                INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` 
                WHERE `subject` LIKE '%" . $text . "%'";

            $users_rs = Database::search($usersQuery);
            $students_rs = Database::search($studentsQuery);
            $intake_students_rs = Database::search($intakeStudentsQuery);
            $faculties_rs = Database::search($facultiesQuery);
            $programmes_rs = Database::search($programmesQuery);
            $intakes_rs = Database::search($intakesQuery);
            $levels_rs = Database::search($levelsQuery);
            $subjects_rs = Database::search($subjectQuery);

            if ($users_rs->num_rows > 0 || $students_rs->num_rows > 0 || $intake_students_rs->num_rows > 0 || $faculties_rs->num_rows > 0 || $programmes_rs->num_rows > 0 || $intakes_rs->num_rows > 0 || $levels_rs->num_rows > 0 || $subjects_rs->num_rows > 0) {

                if ($users_rs->num_rows > 0) {
?>
                    <div id="search-results" class="list-group mt-2">
                        <h6 class="dark-theme text-16 fw-semibold mb-3" data-i18n="users">Users</h6>
                        <?php
                        while ($user = $users_rs->fetch_assoc()) {
                            $user_name = $user["title"] . "." . $user["user_fname"] . " " . $user["user_lname"];
                        ?>
                            <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 bl-solid rounded-0 hover-bg" onclick="moveOnSearchResult('<?= $user_name ?>','./user-details?user-id=<?= $user['user_id']; ?>')">
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <?php
                                    if (!empty($user["profile_img"]) && file_exists('../assets/imgs/users/' . $user["profile_img"])) {
                                    ?>
                                        <img src="./assets/imgs/users/<?php echo $user["profile_img"]; ?>" class="hwr-40" alt="User image">
                                    <?php
                                    } else {
                                        $firstNameInitial = strtoupper(substr($user["user_fname"], 0, 1));
                                        $lastNameInitial = strtoupper(substr($user["user_lname"], 0, 1));
                                    ?>
                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark-color"><?= $user_name ?></div>
                                    <small class="text-muted"><?= $user["user_email"]; ?> | <?= $user["user_mobile"]; ?></small>
                                </div>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                <?php
                }

                if ($students_rs->num_rows > 0) {
                ?>
                    <div id="search-results" class="list-group mt-3">
                        <h6 class="dark-theme text-16 fw-semibold mb-3" data-i18n="students">Students</h6>
                        <?php
                        while ($student = $students_rs->fetch_assoc()) {
                            $student_name = $student["title"] . "." . $student["first_name"] . " " . $student["last_name"];
                        ?>
                            <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 bl-solid rounded-0 hover-bg" onclick="moveOnSearchResult('<?= $student_name ?>','./student-details?student-id=<?= $student['student_id']; ?>')">
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <?php
                                    if (!empty($student["profile_img"]) && file_exists('../assets/imgs/students/' . $student["profile_img"])) {
                                    ?>
                                        <img src="./assets/imgs/students/<?php echo $student["profile_img"]; ?>" class="hwr-40" alt="User image">
                                    <?php
                                    } else {
                                        $firstNameInitial = strtoupper(substr($student["first_name"], 0, 1));
                                        $lastNameInitial = strtoupper(substr($student["last_name"], 0, 1));
                                    ?>
                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark-color"><?= $student["title"] . "." . $student["first_name"] . " " . $student["last_name"]; ?></div>
                                    <small class="text-muted"><?= $student["email"]; ?> | <?= $student["whatsapp"]; ?></small>
                                </div>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                <?php
                }

                if ($intake_students_rs->num_rows > 0) {
                ?>
                    <div id="search-results" class="list-group mt-3">
                        <h6 class="dark-theme text-16 fw-semibold mb-3" data-i18n="intake-students">Intake Has Students</h6>
                        <?php
                        while ($intakeStudent = $intake_students_rs->fetch_assoc()) {
                            $student_name = $intakeStudent["title"] . "." . $intakeStudent["first_name"] . " " . $intakeStudent["last_name"];
                        ?>
                            <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 bl-solid rounded-0 hover-bg" onclick="moveOnSearchResult('<?= $student_name ?>','./intake-student-details?student-id=<?= $intakeStudent['students_has_intakes_id']; ?>')">
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <?php
                                    if (!empty($intakeStudent["profile_img"]) && file_exists('../assets/imgs/students/' . $intakeStudent["profile_img"])) {
                                    ?>
                                        <img src="./assets/imgs/students/<?php echo $intakeStudent["profile_img"]; ?>" class="hwr-40" alt="User image">
                                    <?php
                                    } else {
                                        $firstNameInitial = strtoupper(substr($intakeStudent["first_name"], 0, 1));
                                        $lastNameInitial = strtoupper(substr($intakeStudent["last_name"], 0, 1));
                                    ?>
                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark-color"><?= $intakeStudent["title"] . "." . $intakeStudent["first_name"] . " " . $intakeStudent["last_name"]; ?></div>
                                    <small class="text-muted">Reg No: <?= $intakeStudent["student_id"]; ?></small>
                                </div>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                <?php
                }

                if ($faculties_rs->num_rows > 0) {
                ?>
                    <div id="search-results" class="list-group mt-3">
                        <h6 class="dark-theme text-16 fw-semibold mb-3" data-i18n="faculties">Faculties</h6>
                        <?php
                        while ($faculty = $faculties_rs->fetch_assoc()) {

                        ?>
                            <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 bl-solid rounded-0 hover-bg" onclick="moveOnSearchResult('<?= $faculty['faculty'] ?>','./programes?faculty-id=<?= $faculty['faculty_id']; ?>')">
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <?php
                                    if (!empty($faculty["faculty_cover"]) && file_exists('../assets/imgs/faculties-covers/' . $faculty["faculty_cover"])) {
                                    ?>
                                        <img src="./assets/imgs/faculties-covers/<?php echo $faculty["faculty_cover"]; ?>" class="hw-40 w-auto" alt="User image">
                                    <?php
                                    } else {
                                    ?>
                                        <img src="./assets/imgs/common/empty-thumbnail.webp" class="hw-40 w-auto" alt="User image">
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark-color"><?= $faculty["faculty"] ?></div>
                                    <small class="text-muted">Number of programmes: <?= $faculty['programme_count'] ?></small>
                                </div>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                <?php
                }

                if ($programmes_rs->num_rows > 0) {
                ?>
                    <div id="search-results" class="list-group mt-3">
                        <h6 class="dark-theme text-16 fw-semibold mb-3" data-i18n="programes">Programmes</h6>
                        <?php
                        while ($programme = $programmes_rs->fetch_assoc()) {
                        ?>
                            <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 bl-solid rounded-0 hover-bg" onclick="moveOnSearchResult('<?= $programme['programme'] ?>','./intakes?programe-id=<?= $programme['programme_id']; ?>')">
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <?php
                                    if (!empty($programme["programme_cover"]) && file_exists('../assets/imgs/programmes-covers/' . $programme["programme_cover"])) {
                                    ?>
                                        <img src="./assets/imgs/programmes-covers/<?php echo $programme["programme_cover"]; ?>" class="hw-40 w-auto" alt="User image">
                                    <?php
                                    } else {
                                    ?>
                                        <img src="./assets/imgs/common/empty-thumbnail.webp" class="hw-40 w-auto" alt="User image">
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark-color"><?= $programme["programme"] ?></div>
                                    <small class="text-muted"><?= $programme["faculty"] ?></small>
                                </div>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                <?php
                }

                if ($intakes_rs->num_rows > 0) {
                ?>
                    <div id="search-results" class="list-group mt-3">
                        <h6 class="dark-theme text-16 fw-semibold mb-3" data-i18n="intakes">Intakes</h6>
                        <?php
                        while ($intake = $intakes_rs->fetch_assoc()) {
                        ?>
                            <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 bl-solid rounded-0 hover-bg" onclick="moveOnSearchResult('<?= $intake['intake'] ?>','./intake-details?intake-id=<?= $intake['intake_id']; ?>')">
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <?php
                                    if (!empty($intake["intake_cover"]) && file_exists('../assets/imgs/intakes-covers/' . $intake["intake_cover"])) {
                                    ?>
                                        <img src="./assets/imgs/intakes-covers/<?php echo $intake["intake_cover"]; ?>" class="hw-40 w-auto" alt="User image">
                                    <?php
                                    } else {
                                    ?>
                                        <img src="./assets/imgs/common/empty-thumbnail.webp" class="hw-40 w-auto" alt="User image">
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark-color"><?= $intake["intake"] ?></div>
                                    <small class="text-muted d-inline-flex align-items-center">
                                        <?= strlen($intake["faculty"]) > 40 ? substr($intake["faculty"], 0, 40) . '...' : $intake["faculty"] ?>
                                        <iconify-icon icon="gg:arrow-right" width="20" height="20" class="sub-text mx-1 p-0 my-0"></iconify-icon>
                                        <?= strlen($intake["programme"]) > 40 ? substr($intake["programme"], 0, 40) . '...' : $intake["programme"] ?>
                                    </small>
                                </div>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                <?php
                }

                if ($levels_rs->num_rows > 0) {
                ?>
                    <div id="search-results" class="list-group mt-3">
                        <h6 class="dark-theme text-16 fw-semibold mb-3" data-i18n="levels">Levels</h6>
                        <?php
                        while ($level = $levels_rs->fetch_assoc()) {
                        ?>
                            <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 bl-solid rounded-0 hover-bg" onclick="moveOnSearchResult('<?= $level['level'] ?>','./level-details?level-id=<?= $level['level_id']; ?>')">
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <?php
                                    if (!empty($level["level_cover"]) && file_exists('../assets/imgs/levels-covers/' . $level["level_cover"])) {
                                    ?>
                                        <img src="./assets/imgs/levels-covers/<?php echo $level["level_cover"]; ?>" class="hw-40 w-auto" alt="User image">
                                    <?php
                                    } else {
                                    ?>
                                        <img src="./assets/imgs/common/empty-thumbnail.webp" class="hw-40 w-auto" alt="User image">
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark-color"><?= $level["level"] ?></div>
                                    <small class="text-muted d-inline-flex align-items-center">
                                        <?= strlen($level["faculty"]) > 30 ? substr($level["faculty"], 0, 30) . '...' : $level["faculty"] ?>
                                        <iconify-icon icon="gg:arrow-right" width="20" height="20" class="sub-text mx-1 p-0 my-0"></iconify-icon>
                                        <?= strlen($level["programme"]) > 30 ? substr($level["programme"], 0, 30) . '...' : $level["programme"] ?>
                                        <iconify-icon icon="gg:arrow-right" width="20" height="20" class="sub-text mx-1 p-0 my-0"></iconify-icon>
                                        <?= strlen($level["intake"]) > 30 ? substr($level["intake"], 0, 30) . '...' : $level["intake"] ?>
                                    </small>
                                </div>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                <?php
                }

                if ($subjects_rs->num_rows > 0) {
                ?>
                    <div id="search-results" class="list-group mt-3">
                        <h6 class="dark-theme text-16 fw-semibold mb-3" data-i18n="subjects">Subjects</h6>
                        <?php
                        while ($subject = $subjects_rs->fetch_assoc()) {
                        ?>
                            <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 bl-solid rounded-0 hover-bg" onclick="moveOnSearchResult('<?= $subject['subject'] ?>','./subject-details?subject-id=<?= $subject['subject_id']; ?>')">
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <?php
                                    if (!empty($subject["subject_cover"]) && file_exists('../assets/imgs/subjects-covers/' . $subject["subject_cover"])) {
                                    ?>
                                        <img src="./assets/imgs/subjects-covers/<?php echo $subject["subject_cover"]; ?>" class="hw-40 w-auto" alt="User image">
                                    <?php
                                    } else {
                                    ?>
                                        <img src="./assets/imgs/common/empty-thumbnail.webp" class="hw-40 w-auto" alt="User image">
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold text-dark-color"><?= $subject["subject"] ?></div>
                                    <small class="text-muted d-inline-flex align-items-center">
                                        <?= strlen($subject["faculty"]) > 20 ? substr($subject["faculty"], 0, 20) . '...' : $subject["faculty"] ?>
                                        <iconify-icon icon="gg:arrow-right" width="20" height="20" class="sub-text mx-1 p-0 my-0"></iconify-icon>
                                        <?= strlen($subject["programme"]) > 20 ? substr($subject["programme"], 0, 20) . '...' : $subject["programme"] ?>
                                        <iconify-icon icon="gg:arrow-right" width="20" height="20" class="sub-text mx-1 p-0 my-0"></iconify-icon>
                                        <?= strlen($subject["intake"]) > 20 ? substr($subject["intake"], 0, 20) . '...' : $subject["intake"] ?>
                                        <iconify-icon icon="gg:arrow-right" width="20" height="20" class="sub-text mx-1 p-0 my-0"></iconify-icon>
                                        <?= strlen($subject["level"]) > 20 ? substr($subject["level"], 0, 20) . '...' : $subject["level"] ?>
                                    </small>
                                </div>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                <?php
                }
            } else {
                ?>
                <div class="col-12 py-3">
                    <div class="row align-items-center justify-content-center">
                        <img src="./assets/imgs/common/result-not-found.png" class="no-data-icon" alt="No data" />
                        <h5 class="text-center">No results for "<?= $text ?>"</h5>
                    </div>
                </div>
<?php
            }
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid JSON';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error') || property_exists($status, 'emptyText')) {
    echo (json_encode($status));
}
