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

        // NOTE: SQL/JOINS unchanged from the original query - only the GROUP BY
        // and downstream PHP processing changed. GROUP BY still includes
        // programme_id here on purpose: MySQL can't cleanly nest two levels of
        // JSON_ARRAYAGG (programme -> batches), so we aggregate batches per
        // programme in SQL as before, then merge same-session rows across
        // programmes together in PHP below.
        $totalLecSchedule = "
        SELECT 
            `p`.`programme_id`,
            `p`.`programme`,
          
          MAX(`p`.`programme_cover`) AS `programme_cover`,
          `b`.`branch_id`,
          `b`.`branch`,
            `f`.`faculty_id`,
            `f`.`faculty`,
            `subj`.`subject_id`,
            `subj`.`subject`,
            `tt`.`date`,
            `tt`.`start_time`,
            `tt`.`end_time`,
            `tt`.`lecture`,
            `tt`.`lecture_title`,
            `tt`.`time_table_id`,
            `tts`.`time_table_status`,
            `tts`.`time_table_status_id`,
            `lt`.`lecture_type`,
            `lt`.`lecture_type_id`,
            `u`.`user_id`,
            `u`.`user_fname`,
            `u`.`user_lname`,
            `u`.`profile_img`,
            `t`.`title`,
    
           JSON_ARRAYAGG(
            JSON_OBJECT(
                'lecture_id', `tt`.`time_table_id`,
                'batch_id', `i`.`intake_id`,
                'batch_name', `i`.`intake`,
                'batch_cover', `i`.`intake_cover`,
                'level', `lv`.`level`,
                'intake', `i`.`intake`,
                'subject', `subj`.`subject`,
                'subject_id', `subj`.`subject_id`,
                'programme', `p`.`programme`,
                'programme_id', `p`.`programme_id`,
                'programme_cover', `p`.`programme_cover`,
                'faculty', `f`.`faculty`,
                'faculty_id', `f`.`faculty_id`,
                'branch',`b`.`branch`,
                'lecturer_id', `lec`.`lecturers_id`,
                'lecturer_name', `u`.`user_fname`
            )
        ) AS batch_list,
         COUNT(DISTINCT `tt`.`time_table_id`) AS timetable_count
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
            `branches` AS `b` ON `f`.`branches_branch_id` = `b`.`branch_id`                 
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
           WHERE 1=1
    ";

        if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
            $totalLecSchedule .= " AND 
                      u.user_id = '" . $sessionUserObject["user_id"] . "'";
        }

        if (isset($_GET['subject']) && !empty($_GET['subject'])) {
            $totalLecSchedule .= " AND subj.subject_id = '" . $_GET['subject'] . "'";
        }


        if (isset($_GET['text']) && !empty($_GET['text'])) {
            $search_text = $_GET['text'];
            $totalLecSchedule .= " AND 
            (`i`.`intake` LIKE '%" . $search_text . "%' OR 
            CONCAT(`u`.`user_fname`, ' ', `u`.`user_lname`) LIKE '%" . $search_text . "%' OR 
            `subj`.`subject` LIKE '%" . $search_text . "%' OR
            `lv`.`level` LIKE '%" . $search_text . "%')";
        }


        $totalLecSchedule .= "  AND lt.lecture_type_id != 3
                GROUP BY
            `p`.`programme_id`,
            `tt`.`date`,
            `tt`.`start_time`,
            `tt`.`end_time`,
            `tts`.`time_table_status_id`,
            `lt`.`lecture_type_id`,
            `u`.`user_id`          
        ORDER BY
            `tt`.`date` ASC,
            `tt`.`start_time` ASC";

        $totalLecSchedule = Database::search($totalLecSchedule);

        // sessions: keyed by date|start_time|end_time|lecturer|lecture_type
        // this is what merges the same physical lecture slot across multiple
        // programmes into a single entry instead of duplicating the card.
        $sessions = [];
        $sessionOrder = []; // preserves original ORDER BY sequence

        if ($totalLecSchedule->num_rows > 0) {
            $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));

            while ($row = $totalLecSchedule->fetch_assoc()) {

                $programmeCover = "./assets/imgs/programmes-covers/" . $row['programme_cover'];
                $defaultImage = "./assets/imgs/common/empty-thumbnail.webp";

                $hasProgrammeCover = true;
                if (empty($row['programme_cover'])) {
                    $hasProgrammeCover = false;
                } else if (!file_exists("../assets/imgs/programmes-covers/" . $row['programme_cover'])) {
                    $hasProgrammeCover = false;
                }

                $lectureStatus = $row['time_table_status'];

                // attendance is counted per timetable row group (this programme's
                // batches for this session) - summed onto the session below
                $attendanceCount = 0;
                $batchListDecoded = json_decode($row['batch_list'], true);
                if (is_array($batchListDecoded)) {
                    foreach ($batchListDecoded as $b) {
                        if (isset($b['lecture_id'])) {
                            $attendanceCount += (int) Database::search("SELECT COUNT(*) AS count FROM `attendance` WHERE `time_table_time_table_id` = '" . $b['lecture_id'] . "' ")->fetch_assoc()['count'];
                        }
                    }
                }

                if ($row['time_table_status_id'] == 1) {
                    if ($row['date'] == $date->format('Y-m-d') && $row['start_time'] < $date->format('H:i:s') && $row['end_time'] > $date->format('H:i:s')) {
                        $lectureStatus = "Ongoing";
                    } else if (($row['date'] == $date->format('Y-m-d') && $row['start_time'] > $date->format('H:i:s')) || $row['date'] > $date->format('Y-m-d')) {
                        $lectureStatus =  "Upcoming";
                    } else if (($row['date'] == $date->format('Y-m-d') && $row['end_time'] < $date->format('H:i:s')) || $row['date'] < $date->format('Y-m-d')) {
                        $lectureStatus = 'Completed';
                    }
                }

                $hasPermissionEditPermission = SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', 0);
                $hasPermissionConfirmLecturerPayment = SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', 0);
                $hasPermissionMarkAttendance = SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', 0);
                $hasPermissionUploadRecording = SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule');

                // programme-level block (one per programme sharing this session)
                // programme-level block (one per programme sharing this session)
                $programmeEntry = [
                    'programmeId' => $row['programme_id'],
                    'programme' => htmlspecialchars($row['programme']),
                    'programmeImage' => $hasProgrammeCover ? $programmeCover : $defaultImage,
                    'faculty' => htmlspecialchars($row['faculty']),
                    'branch' => htmlspecialchars($row['branch']),        // <-- NEW
                    'branchId' => $row['branch_id'],                     // <-- NEW
                    'batchList' => $batchListDecoded,
                    'timetableCount' => (int) $row['timetable_count'],
                    'attendanceCount' => $attendanceCount,
                ];
                // key that identifies "this is the same physical lecture slot"
                $sessionKey = implode('|', [
                    $row['date'],
                    $row['start_time'],
                    $row['end_time'],
                    $row['user_id'],
                    $row['lecture_type_id'],
                    $row['time_table_status_id'],
                ]);

                if (!isset($sessions[$sessionKey])) {
                    $sessions[$sessionKey] = [
                        'sessionKey' => $sessionKey,
                        'lectureId' => $row['time_table_id'], // primary/first tt id - used for legacy single-programme actions
                        'date' => date('d F, Y', strtotime($row['date'])),
                        'rawDate' => $row['date'],
                        'lecturerName' => $row['title'] . '.' . $row['user_fname'] . ' ' . $row['user_lname'],
                        'lecturerProfile' => "./assets/imgs/users/" . $row['profile_img'],
                        'joinLink' => $row['lecture'],
                        'status' => $lectureStatus,
                        'startTime' => date('h:i A', strtotime($row['start_time'])),
                        'endTime' => date('h:i A', strtotime($row['end_time'])),
                        'lectureType' => $row['lecture_type'],
                        'title' => $row['lecture_title'],
                        'hasPermissionEditPermission' => $hasPermissionEditPermission,
                        'hasPermissionConfirmLecturerPayment' => $hasPermissionConfirmLecturerPayment,
                        'hasPermissionMarkAttendance' => $hasPermissionMarkAttendance,
                        'hasPermissionUploadRecording' => $hasPermissionUploadRecording,
                        'programmes' => [],
                        'attendanceCount' => 0,
                        // kept for backward compatibility with any older frontend code
                        // reading a flat 'subject' / 'batchList' / 'programme' off a lecture
                        'subject' => $row['subject'],
                        'programme' => htmlspecialchars($row['programme']),
                        'branch' => htmlspecialchars($row['branch']),
                        'programmeImage' => $hasProgrammeCover ? $programmeCover : $defaultImage,
                        'batchList' => [],
                    ];
                    $sessionOrder[] = $sessionKey;
                }

                $sessions[$sessionKey]['programmes'][] = $programmeEntry;
                $sessions[$sessionKey]['attendanceCount'] += $attendanceCount;
                $sessions[$sessionKey]['batchList'] = array_merge($sessions[$sessionKey]['batchList'], $batchListDecoded ?: []);
            }
        }

        $lectures = [];
        foreach ($sessionOrder as $sessionKey) {
            $session = $sessions[$sessionKey];
            $session['programmeCount'] = count($session['programmes']);
            $session['isConcurrent'] = $session['programmeCount'] > 1;
            unset($session['sessionKey']);

            $lectureDate = $session['rawDate'];
            unset($session['rawDate']);

            if (!isset($lectures[$lectureDate])) {
                $lectures[$lectureDate] = [];
            }
            $lectures[$lectureDate][] = $session;
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
