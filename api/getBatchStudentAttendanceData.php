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

        $id = $_GET['student'];

        $studentHasIntake = Database::search("SELECT
        `faculty`,
        `faculty_id`,
        `programme`,
        `programme_id`,
        `intake`,
        `intake_id`,
        `students_has_intakes`.`student_id`,
        `students`.`student_id` AS `admissionId`,
        `first_name`,
        `last_name`,
        `title`,
        `students_has_intakes_id`
    FROM `students_has_intakes`
    INNER JOIN `students` 
        ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
    INNER JOIN `login_details`
        ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`    
    INNER JOIN `title` 
        ON `students`.`title_title_id` = `title`.`title_id`
    INNER JOIN `intakes` 
        ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
  
    INNER JOIN `programmes` 
        ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
    INNER JOIN `faculties` 
        ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
    WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $id . "'
");


        if ($studentHasIntake->num_rows == 0) {
            $status->error = 'Invalid request';
        } else {
            $studentHasIntakeData = $studentHasIntake->fetch_assoc();

            $totalAttendanceQuery = "
        SELECT 
            subj.subject_id,
            subj.subject,
            subj.subject_type_subject_type_id,
            tt.time_table_id,
            tt.date,
            tt.start_time,
            tt.end_time,
            a.attendance_status_attendance_status_id,
            u.user_fname,
            u.user_lname,
            u.profile_img,
            subj.subject_cover,
            t.title
        FROM 
            subjects AS subj
        INNER JOIN 
            time_table AS tt ON subj.subject_id = tt.subjects_subject_id
        INNER JOIN 
            lecturers AS lec ON tt.lecturers_lecturers_id = lec.lecturers_id
        INNER JOIN 
            users AS u ON lec.users_user_id = u.user_id
        INNER JOIN 
            title AS t ON u.title_title_id = t.title_id            
        LEFT JOIN 
            attendance AS a ON tt.time_table_id = a.time_table_time_table_id
                AND a.students_student_id = '" . $studentHasIntakeData['admissionId'] . "'
        WHERE 
            subj.levels_level_id IN (
                SELECT level_id 
                FROM levels 
                WHERE intakes_intake_id = '" . $studentHasIntakeData['intake_id'] . "'
            )
            AND (
                subj.subject_type_subject_type_id = 1
                OR (
                    subj.subject_type_subject_type_id = 2
                    AND EXISTS (
                        SELECT 1 
                        FROM students_has_subjects shs 
                        WHERE shs.students_student_id = '" . $studentHasIntakeData['admissionId'] . "'
                        AND shs.subjects_subject_id = subj.subject_id
                    )
                )
            )
                AND TIMESTAMP(tt.date, tt.start_time) <= '" . $date->format('Y-m-d H:i:s') . "'
                 AND tt.time_table_status_time_table_status_id = 1 AND lecture_type_lecture_type_id != 3
       
    ";

            $totalAttendanceQuery .= "  ORDER BY tt.date, tt.start_time";

            $totalAttendances = Database::search($totalAttendanceQuery);
            $lectures = []; // This will hold our calendar data structure

            if ($totalAttendances->num_rows > 0) {
                $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));

                while ($row = $totalAttendances->fetch_assoc()) {
                    // Format the date as YYYY-MM-DD for the key
                    $dateFormat = (new DateTime($row['date'], new DateTimeZone("Asia/Colombo")))->format('Y-m-d');
                    $lectureDateTime = new DateTime($row['date'] . ' ' . $row['start_time'], new DateTimeZone("Asia/Colombo"));
                    $lectureEnd = new DateTime($row['date'] . ' ' . $row['end_time'], new DateTimeZone("Asia/Colombo"));
                    // Create lecture entry
                    $lecturerName = $row['title'] . "." . $row['user_fname'] . " " . $row['user_lname'];

                    if ($row['attendance_status_attendance_status_id'] === '1') {
                        if ($lectureEnd < $date) {
                            $attendanceStatus = 'Absent';
                        } else {
                            $attendanceStatus = 'Pending';
                        }
                    } elseif ($row['attendance_status_attendance_status_id'] === '2') {
                        $attendanceStatus = 'Present';
                    } elseif ($row['attendance_status_attendance_status_id'] === '3') {
                        $attendanceStatus = 'Absent';
                    } else if ($row['attendance_status_attendance_status_id'] === '4') {
                        $attendanceStatus = 'Watched';
                    } else {
                        $attendanceStatus = 'Absent';
                    }

                    $subjectImage = "./assets/imgs/subjects-covers/" . $row['subject_cover'];
                    $lecture = [
                        'subject' => htmlspecialchars($row['subject']),
                        'lecturerName' => htmlspecialchars($lecturerName),
                        'subjectImage' => $subjectImage,
                        'status' => $attendanceStatus,
                        'startTime' => date('h:i A', strtotime($row['start_time'])),
                        'endTime'   => date('h:i A', strtotime($row['end_time'])),
                        'lectureId' => $row['time_table_id'],
                        'lecturer' => $row['title'] . " " . $row['user_fname'] . " " . $row['user_lname'],
                        'date' => $row['date']
                    ];

                    if (!isset($lectures[$dateFormat])) {
                        $lectures[$dateFormat] = [];
                    }
                    $lectures[$dateFormat][] = $lecture;
                }
            }

            $status->lectures = $lectures;
            $status->done = true;
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid JSON';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
