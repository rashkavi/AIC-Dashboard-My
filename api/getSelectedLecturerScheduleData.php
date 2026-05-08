<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lecturer']) && !empty($_GET['lecturer'])) {
        $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));

        $lecturerId = $_GET['lecturer'];

        $totalLecSchedule = "
                SELECT 
                    subj.subject_id,
                    subj.subject,
                    subj.subject_cover,
                    subj.subject_type_subject_type_id,
                    tt.time_table_id,
                    tt.date,
                    tt.start_time,
                    tt.end_time,
                    tts.time_table_status,
                    tts.time_table_status_id,
                    lt.lecture_type,
                    u.user_fname,
                    u.user_lname,
                    u.profile_img,
                    t.title
                FROM                    
                    time_table AS tt
                INNER JOIN 
                    subjects AS subj ON tt.subjects_subject_id = subj.subject_id
                INNER JOIN
                    time_table_status AS tts ON tt.time_table_status_time_table_status_id = tts.time_table_status_id
                INNER JOIN
                    lecture_type AS lt ON tt.lecture_type_lecture_type_id = lt.lecture_type_id        
                INNER JOIN 
                    lecturers AS lec ON tt.lecturers_lecturers_id = lec.lecturers_id
                INNER JOIN 
                    users AS u ON lec.users_user_id = u.user_id
                INNER JOIN 
                    title AS t ON u.title_title_id = t.title_id            
                WHERE 
                      lec.lecturers_id = '" . $lecturerId . "'
                      AND lt.lecture_type_id != 3
                ORDER BY tt.date, tt.start_time
            ";

        $totalLecSchedule = Database::search($totalLecSchedule);
        $lectures = [];

        if ($totalLecSchedule->num_rows > 0) {
            $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));
            while ($row = $totalLecSchedule->fetch_assoc()) {

                $subjectImage = "https://dashboard.timesacademylms.com/assets/imgs/subjects-covers/" . $row['subject_cover'];
                $defaultImage = "https://dashboard.timesacademylms.com/assets/imgs/common/empty-thumbnail.webp";

                $hasSubjectImage = true;
                if (empty($row['subject_cover'])) {
                    $hasSubjectImage = false;
                } else if (!file_exists('https://dashboard.timesacademylms.com/assets/imgs/subjects-covers/' . $row['subject_cover'])) {
                    $hasSubjectImage = false;
                }

                $lectureStatus = $row['time_table_status'];

                if ($row['time_table_status_id'] == 1) {
                    if ($row['date'] == $date->format('Y-m-d') || $row['date'] > $date->format('Y-m-d')) {
                        $lectureStatus =  "Upcoming";
                    } else if ($row['date'] < $date->format('Y-m-d')) {
                        $lectureStatus = 'Completed';
                    }
                }

                $lecture = [
                    'subject' => htmlspecialchars($row['subject']),
                    'subjectImage' => $hasSubjectImage ? $subjectImage : $defaultImage,
                    'status' => $lectureStatus,
                    'startTime' => date('h:i A', strtotime($row['start_time'])),
                    'endTime' => date('h:i A', strtotime($row['end_time'])),
                    'lectureType' => $row['lecture_type'],
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
