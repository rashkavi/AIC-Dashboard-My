<?php
class LecturerDashboard
{
    public static function loadContent()
    {
        $sessionUserRole = SessionUser::getCurrentUserRole();
        $sessionUserObject = SessionUser::getCurrentUserObject();

        if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
            $id = $sessionUserObject['user_id'];
            $lecturer = Database::search("SELECT `user_id` FROM  `users` 
            WHERE `users`.`user_id` = '" . $id . "' AND `users`.`status_status_id` = '1'");

            if ($lecturer->num_rows > 0) {
                $timezone = new DateTimeZone("Asia/Colombo");
                $dateTime = new DateTime("now", $timezone);
                $todayDate = $dateTime->format('Y-m-d');
                $nowTime = $dateTime->format('H:i:s');

                $totalCompletedHourse = 0;
                $maxCourseHours = 0;
                $completeProgress = 0;
                $assignementsProgress = 0;
                $submittedAssignments = 0;
                $notSubmittedAssignments = 0;
                $totalAssignments = 0;

                $totalIncome = 0;
                $pendingPayments = 0;
                $rejectedPayments = 0;

                $lecturerSubjects = Database::search("SELECT `lecturers_id`,`subject_id`,`max_course_hours` FROM `lecturers`
                INNER JOIN `subjects` ON `lecturers`.`subjects_subject_id` = `subjects`.`subject_id`
                WHERE `lecturers`.`users_user_id` = '" . $id . "'");

                if ($lecturerSubjects->num_rows != 0) {

                    while ($lecturerData = $lecturerSubjects->fetch_assoc()) {
                        $result = Database::search("
                                        SELECT SUM(`duration`) AS total_minutes
                        FROM `time_table`
                        WHERE `subjects_subject_id` = '" . $lecturerData['subject_id'] . "'
                        AND `lecturers_lecturers_id` = '" . $lecturerData['lecturers_id'] . "'
                        AND (
                            (`date` = '" . $todayDate . "' 
                                AND `start_time` <= '" . $nowTime . "' 
                                AND `end_time` <= '" . $nowTime . "')
                            OR `date` < '" . $todayDate . "'
                        )
                        AND `time_table`.`time_table_status_time_table_status_id` = '1'

                                    ");

                        $totalCompletedHourse += $result->fetch_assoc()['total_minutes'];
                        $maxCourseHours += floatval($lecturerData['max_course_hours']);

                        $assignements = Database::search("SELECT 
                COUNT(*) AS total_count,
                IFNULL(SUM(CASE WHEN lecturers_has_students_has_assignments.submission_sts_submission_sts_id = 2 THEN 1 ELSE 0 END), 0) AS submitted_count,
                IFNULL(SUM(CASE WHEN lecturers_has_students_has_assignments.submission_sts_submission_sts_id = 1 THEN 1 ELSE 0 END), 0) AS not_submitted_count
            FROM 
                lecturers_has_students_has_assignments
            INNER JOIN 
                students_has_assignments 
                ON lecturers_has_students_has_assignments.students_has_assignments_students_has_assignments_id = students_has_assignments.students_has_assignments_id
            INNER JOIN
                assignments 
                ON students_has_assignments.assignments_assignment_id = assignments.assignment_id
            WHERE 
                lecturers_has_students_has_assignments.lecturers_lecturers_id = '" . $id . "'
                AND assignments.subjects_subject_id = '" . $lecturerData['subject_id'] . "'
            ");
                        $assignementsData = $assignements->fetch_assoc();

                        $totalAssignments += $assignementsData['total_count'];
                        $submittedAssignments += $assignementsData['submitted_count'];
                        $notSubmittedAssignments += $assignementsData['not_submitted_count'];

                        $assignmentsPayments = Database::search("SELECT * FROM `assignment_payments`
                INNER JOIN `assignments` ON `assignment_payments`.`assignments_assignment_id` = `assignments`.`assignment_id` 
                WHERE `assignment_payments`.`lecturers_lecturers_id` = '" . $id . "' AND `subjects_subject_id` = '" . $lecturerData['subject_id'] . "'");

                        if ($assignmentsPayments->num_rows > 0) {
                            while ($assignmentPayment = $assignmentsPayments->fetch_assoc()) {
                                if ($assignmentPayment['earning_status_earning_status_id'] == 1) {
                                    $pendingPayments += $assignmentPayment['amount'];
                                } else if ($assignmentPayment['earning_status_earning_status_id'] == 2) {
                                    $totalIncome += $assignmentPayment['amount'];
                                } else if ($assignmentPayment['earning_status_earning_status_id'] == 3) {
                                    $rejectedPayments += $assignmentPayment['amount'];
                                }
                            }
                        }

                        $examPayments = Database::search("SELECT * FROM `exam_payments`
                INNER JOIN `exams` ON `exam_payments`.`exams_exam_id` = `exams`.`exam_id` 
                WHERE `exam_payments`.`lecturers_lecturers_id` = '" . $id . "' AND `subjects_subject_id` = '" . $lecturerData['subject_id'] . "'");

                        if ($examPayments->num_rows > 0) {
                            while ($examPayment = $examPayments->fetch_assoc()) {
                                if ($examPayment['earning_status_earning_status_id'] == 1) {
                                    $pendingPayments += $examPayment['amount'];
                                } else if ($examPayment['earning_status_earning_status_id'] == 2) {
                                    $totalIncome += $examPayment['amount'];
                                } else if ($examPayment['earning_status_earning_status_id'] == 3) {
                                    $rejectedPayments += $examPayment['amount'];
                                }
                            }
                        }

                        $lecturePayments = Database::search("SELECT * FROM `lecturer_payments`
                INNER JOIN `time_table` ON `lecturer_payments`.`time_table_time_table_id` = `time_table`.`time_table_id` 
                WHERE `lecturer_payments`.`lecturers_lecturers_id` = '" . $id . "' AND `subjects_subject_id` = '" . $lecturerData['subject_id'] . "'");

                        if ($lecturePayments->num_rows > 0) {
                            while ($lecturePayment = $lecturePayments->fetch_assoc()) {
                                if ($lecturePayment['earning_status_earning_status_id'] == 1) {
                                    $pendingPayments += $lecturePayment['amount'];
                                } else if ($lecturePayment['earning_status_earning_status_id'] == 2) {
                                    $totalIncome += $lecturePayment['amount'];
                                } else if ($lecturePayment['earning_status_earning_status_id'] == 3) {
                                    $rejectedPayments += $lecturePayment['amount'];
                                }
                            }
                        }
                    }

                    $completeProgress += $maxCourseHours > 0
                        ? ($totalCompletedHourse / ($maxCourseHours * 60)) * 100
                        : 0;

                    if ($totalAssignments > 0) {
                        $assignementsProgress += ($totalAssignments / $submittedAssignments) * 100;
                    }

                    $completeProgress = round($completeProgress, 2);
                    $assignementsProgress = round($assignementsProgress, 2);
                }

                $hours = floor($totalCompletedHourse / 60);
                $minutes = $totalCompletedHourse % 60;


?>
                <div class="container p-0 m-0 g-0 mb-4">
                    <div class="row g-4 d-flex">
                        <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                            <div class="component-theme p-3 w-100">
                                <div class="d-flex flex-row align-items-center">
                                    <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:ac-unit" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Completed Lecture Hours</div>
                                                <div class="stat-value mb-0 text-gray-800 fw-semibold text-24"><?= $totalCompletedHourse == null ? '0' : $hours . 'h ' . $minutes . 'm' ?></div>
                                                <div class="text-xs text-muted">of <?= $maxCourseHours ?> hours (<?= $completeProgress ?>%)</div>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="progress progress-sm" style="height: 0.5rem;">
                                                <div class="progress-bar" role="progressbar" style="width: <?= $completeProgress ?>%" aria-valuenow="<?= $completeProgress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                            <div class="component-theme p-3 w-100">
                                <div class="d-flex flex-row align-items-center">
                                    <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:folder-check-2-outline-rounded" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Marked Assignments</div>
                                                <div class="stat-value mb-0 text-gray-800 fw-semibold text-24"><?= $submittedAssignments ?></div>
                                                <div class="text-xs text-muted">of <?= $notSubmittedAssignments ?> hours (<?= $assignementsProgress ?>%)</div>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="progress progress-sm" style="height: 0.5rem;">
                                                <div class="progress-bar" role="progressbar" style="width:<?= $assignementsProgress ?>%" aria-valuenow="<?= $assignementsProgress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                            <div class="component-theme p-3 w-100">
                                <div class="d-flex flex-row align-items-center">
                                    <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:list-alt-check-outline" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Marked Exams</div>
                                                <div class="stat-value mb-0 text-gray-800 fw-semibold text-24">0</div>
                                                <div class="text-xs text-muted">of 0 hours (0%)</div>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="progress progress-sm" style="height: 0.5rem;">
                                                <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                            <div class="component-theme p-3 w-100">
                                <div class="d-flex flex-row align-items-center">
                                    <div class="hwr-70 table-btn-green-blue d-flex align-items-center justify-content-center"> <iconify-icon icon="icon-park-twotone:flash-payment" width="32" height="32" style="color: var(--dark-green-blue);"></iconify-icon></div>
                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    TOTAL INCOME</div>
                                                <div class="stat-value mb-0 text-gray-800 fw-semibold text-24">LKR <?= number_format($totalIncome) ?></div>
                                                <div class="text-xs text-muted">Available for withdrawal</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                            <div class="component-theme p-3 w-100">
                                <div class="d-flex flex-row align-items-center">
                                    <div class="hwr-70 table-btn-yellow d-flex align-items-center justify-content-center"> <iconify-icon icon="carbon:condition-wait-point" width="32" height="32" style="color: var(--dark-yellow);"></iconify-icon></div>
                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-dark-yellow text-uppercase mb-1">
                                                    PENDING APPROVAL</div>
                                                <div class="stat-value mb-0 text-gray-800 fw-semibold text-24">LKR <?= number_format($pendingPayments) ?></div>
                                                <div class="text-xs text-muted">Next payment cycle</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6 col-xxl-4 d-flex align-items-stretch">
                            <div class="component-theme p-3 w-100">
                                <div class="d-flex flex-row align-items-center">
                                    <div class="hwr-70 table-btn-danger d-flex align-items-center justify-content-center"> <iconify-icon icon="material-symbols:pending" width="32" height="32" style="color: var(--dark-red);"></iconify-icon></div>
                                    <div class="d-flex flex-column flex-grow-1 px-4">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                    REJECTED Payments</div>
                                                <div class="stat-value mb-0 text-gray-800 fw-semibold text-24">LKR <?= number_format($rejectedPayments) ?></div>
                                                <div class="text-xs text-muted">Rejected payments</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container p-0 g-0">
                    <div class="row g-4 d-flex">
                        <div class="col-12 col-xl-8 d-flex">
                            <div class="d-flex flex-column component-theme p-4 w-100">
                                <div class="d-flex flex-row">
                                    <div class="col-8 d-flex flex-column">
                                        <span class="sub-topic">Week Busy Timeline</span>
                                    </div>
                                </div>
                                <div class="component-theme d-flex flex-row align-items-center justify-content-center p-0 mt-2 w-100">
                                    <canvas id="busyTimeline" class="w-100 h-auto"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4 d-flex">
                            <div class="d-flex flex-column component-theme p-4 w-100">
                                <div class="d-flex flex-row p-0 m-0">
                                    <span class="sub-topic">Today Upcoming Lecturers</span>
                                </div>
                                <?php
                                $dateTime = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                                $todayLectures = Database::search("SELECT `subject_cover`,`lecture_type`,
                                                        TIME_FORMAT(`start_time`, '%h:%i %p') AS start_time, 
                                                        TIME_FORMAT(`end_time`, '%h:%i %p') AS end_time,`subject`
                                                        FROM `time_table`
                                                        INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                                                        INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                                        INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                                                        INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                        INNER JOIN `lecture_type` ON `time_table`.`lecture_type_lecture_type_id` = `lecture_type`.`lecture_type_id`
                                                        WHERE `date` = '" . $dateTime->format("Y-m-d") . "'
                                                        AND `end_time` >= '" . $dateTime->format("H:i:s") . "'
                                                        AND `lecturers_lecturers_id` IN (SELECT `lecturers_id` FROM `lecturers` WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "') 
                                                        ORDER BY `start_time` ASC LIMIT 4");

                                if ($todayLectures->num_rows > 0) {
                                    while ($data = $todayLectures->fetch_assoc()) {
                                ?>
                                        <div class="col-12 align-items-center bl-solid p-2 mt-3">
                                            <div class="row align-items-center">
                                                <div class="col-4 align-items-center justify-content-center">
                                                    <?php
                                                    if (!empty($data["subject_cover"]) && file_exists('https://dashboard.timesacademylms.com/assets/imgs/subjects-covers/' . $data["subject_cover"])) {
                                                    ?>
                                                        <img src="https://dashboard.timesacademylms.com/assets/imgs/subjects-covers/<?= $data["subject_cover"]; ?>" class="w-100 h-auto" alt="Subject Cover">
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <img src="https://dashboard.timesacademylms.com/assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="Subject Cover">
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                                <div class="col-8">
                                                    <div class="row">
                                                        <span class="text-16 fw-semibold"><?= $data["subject"]; ?></span>
                                                        <div class="d-felx flex-row align-items-center mt-1">
                                                            <span class="text-14 sub-text"><?= $data["lecture_type"]; ?> Lecture | <?= $data["start_time"]; ?> - <?= $data["end_time"]; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                    }
                                } else {
                                    ?>
                                    <div class="col-12">
                                        <div class="row justify-content-center">
                                            <div class="col-6 col-lg-12">
                                                <img src="./assets/imgs/common/no-data.png" class="w-100 h-auto" alt="Result Not Found"/>
                                            </div>
                                        </div>
                                    </div>
                                <?php
                                }
                                ?>

                            </div>
                        </div>
                    </div>
                </div>
<?php
            }
        }
    }
}
