<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id']) && !empty($_GET['id'])) {
            $exam = isset($_GET['id']) ? $_GET['id'] : 1;
            if (empty($exam) || $exam == 0) {
                $status->error = 'Invalid exam';
            } else {
               
                $examination = Database::search("SELECT * FROM `exams`
                INNER JOIN `online_exam` ON `exams`.`exam_id`=`online_exam`.`exams_exam_id` 
                WHERE `exam_id` = '" . $exam . "' AND `exam_types_exam_type_id` = '2'");
                if ($examination->num_rows == 0) {
                    $status->error = 'Invalid exam';
                } else {
                    $examinationData = $examination->fetch_assoc();

                    $totalStudents = Database::search("SELECT COUNT(*) as count FROM `students_has_intakes` 
                    WHERE `intakes_intake_id` = (SELECT `intakes_intake_id` 
                    FROM `levels` WHERE `level_id`= (SELECT `levels_level_id` FROM `subjects` 
                    WHERE `subject_id`='" . $examinationData['subjects_subject_id'] . "'))")->fetch_assoc()['count'];

                    $examStudents = Database::search("SELECT 
                            COUNT(*) as total_students,
                            COUNT(CASE WHEN `exam_status_exam_status_id` = 1 THEN 1 END) as status_1_count,
                            COUNT(CASE WHEN `exam_status_exam_status_id` = 2 THEN 1 END) as status_2_count,
                            COUNT(CASE WHEN `exam_status_exam_status_id` = 3 THEN 1 END) as status_3_count
                        FROM `students_has_online_exam` 
                        WHERE `online_exam_online_exam_id` = (
                            SELECT `online_exam_id` 
                            FROM `online_exam` 
                            WHERE `exams_exam_id` = '" . $exam . "'
                        )")->fetch_assoc();

                    $submitStudents = (int) $examStudents['total_students'];
                    $notSubmitStudents = (int)$totalStudents - $submitStudents;
                    $pending = (int)$examStudents['status_1_count'];
                    $markReleased = (int)$examStudents['status_3_count'];
                    $markSubmit = (int)$examStudents['status_2_count'];

                    $labels = ["Complete", "Not-Complete"];
                    $data = [$submitStudents, $notSubmitStudents];

                    $labels2 = ["Pending", "Mark Submit",  "Marks Released"];
                    $data2 = [$pending, $markSubmit, $markReleased];

                    $status->labels = $labels;
                    $status->data = $data;

                    $status->labels2 = $labels2;
                    $status->data2 = $data2;
                }
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
