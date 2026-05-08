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
        $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));

        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        $totalLecSchedule = "
        SELECT 
            `subj`.`subject_id`,
            `subj`.`subject`,
            `subj`.`subject_cover`,
            `subj`.`subject_type_subject_type_id`,
            `subj`.`subject_group_subject_group_id`,
            `lv`.`level`,
            `i`.`intake`,
            `p`.`programme`,
            `f`.`faculty`,
            `tt`.`time_table_id`,
            `tt`.`date`,
            `tt`.`start_time`,
            `tt`.`end_time`,
            `tt`.`lecture`,
            `tt`.`lecture_title`,
            `tts`.`time_table_status`,
            `tts`.`time_table_status_id`,
            `lt`.`lecture_type`,
            `u`.`user_fname`,
            `u`.`user_lname`,
            `u`.`profile_img`,
            `t`.`title`
        FROM                    
            `time_table` AS `tt`
        INNER JOIN 
            `subjects` AS `subj` ON `tt`.`subjects_subject_id` = `subj`.`subject_id`
        INNER JOIN 
            `levels` AS `lv` ON `subj`.`levels_level_id` = `lv`.`level_id`
        INNER JOIN 
            `intakes` AS `i` ON `lv`.`intakes_intake_id` = `i`.`intake_id`
        INNER JOIN 
            `programmes` AS `p` ON `i`.`programmes_programme_id` = `p`.`programme_id`
        INNER JOIN
            `faculties` AS `f` ON `p`.`faculties_faculty_id` = `f`.`faculty_id`                
        INNER JOIN
            `time_table_status` AS `tts` ON `tt`.`time_table_status_time_table_status_id` = `tts`.`time_table_status_id`
        INNER JOIN
            `lecture_type` AS `lt` ON `tt`.`lecture_type_lecture_type_id` = `lt`.`lecture_type_id`        
        INNER JOIN 
            `lecturers` AS `lec` ON `tt`.`lecturers_lecturers_id` = `lec`.`lecturers_id`
        INNER JOIN 
            `users` AS `u` ON `lec`.`users_user_id` = `u`.`user_id`
        INNER JOIN 
            `title` AS `t` ON `u`.`title_title_id` = `t`.`title_id`
    ";


        if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
            $totalLecSchedule .= " WHERE 
                      u.user_id = '" . $sessionUserObject["user_id"] . "'";
        }

        if (isset($_GET['subject']) && !empty($_GET['subject'])) {
            $totalLecSchedule .= " AND subj.subject_id = '" . $_GET['subject'] . "'";
        }


        $totalLecSchedule .= "  AND lt.lecture_type_id != 3
                ORDER BY tt.date, tt.start_time";

        $totalLecSchedule = Database::search($totalLecSchedule);
        $lectures = [];

        if ($totalLecSchedule->num_rows > 0) {
            $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));
            while ($row = $totalLecSchedule->fetch_assoc()) {

                $subjectImage = "./assets/imgs/subjects-covers/" . $row['subject_cover'];
                $defaultImage = "./assets/imgs/common/empty-thumbnail.webp";

                $hasSubjectImage = true;
                if (empty($row['subject_cover'])) {
                    $hasSubjectImage = false;
                } else if (!file_exists('../assets/imgs/subjects-covers/' . $row['subject_cover'])) {
                    $hasSubjectImage = false;
                }

                $lectureStatus = $row['time_table_status'];

                if ($row['time_table_status_id'] == 1) {
                    if ($row['date'] == $date->format('Y-m-d') && $row['start_time'] < $date->format('H:i:s') && $row['end_time'] > $date->format('H:i:s')) {
                        $lectureStatus = "Ongoing";
                    } else if (($row['date'] == $date->format('Y-m-d') && $row['start_time'] > $date->format('H:i:s')) || $row['date'] > $date->format('Y-m-d')) {
                        $lectureStatus =  "Upcoming";
                    } else if (($row['date'] == $date->format('Y-m-d') && $row['end_time'] < $date->format('H:i:s')) || $row['date'] < $date->format('Y-m-d')) {
                        $lectureStatus = 'Completed';
                    }
                }

                if ($row['subject_type_subject_type_id'] == 2) {
                    $subjectGroup = Database::search("SELECT * FROM `subject_group` WHERE `subject_group_id` = '" . $row['subject_group_subject_group_id'] . "'");
                    $subjectGroup = $subjectGroup->fetch_assoc()['subject_group'];
                }
                $lecture = [
                    'lectureId' => $row['time_table_id'],
                    'date' => date_format(new DateTime($row['date']), 'D M Y'),
                    'subject' => htmlspecialchars($row['subject']),
                    'subjectType' => $row['subject_type_subject_type_id'] == 1 ? 'Core Subject' : $subjectGroup,
                    'level' => htmlspecialchars($row['level']),
                    'intake' => htmlspecialchars($row['intake']),
                    'programme' => htmlspecialchars($row['programme']),
                    'faculty' => htmlspecialchars($row['faculty']),
                    'subjectImage' => $hasSubjectImage ? $subjectImage : $defaultImage,
                    'lecturerName' => $row['title'] . '.' . $row['user_fname'] . ' ' . $row['user_lname'],
                    'joinLink' => $row['lecture'],
                    'status' => $lectureStatus,
                    'startTime' => date('h:i A', strtotime($row['start_time'])),
                    'endTime' => date('h:i A', strtotime($row['end_time'])),
                    'lectureType' => $row['lecture_type'],
                    'title' => $row['lecture_title'],
                    'hasPermissionEditPermission' => SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 2),
                    'hasPermissionConfirmLecturerPayment' => SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 24),
                    'hasPermissionMarkAttendance' => SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 27),
                    'hasPermissionUploadRecording' => SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 28),
                ];

                $lectureDate = $row['date'];

                if (!isset($lectures[$lectureDate])) {
                    $lectures[$lectureDate] = [];
                }
                $lectures[$lectureDate][] = $lecture;
            }
        }

        $status->lectures = $lectures;
        $status->done = true;
    } else {
        http_response_code(400);
        $status->error = 'Invalid JSON';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
