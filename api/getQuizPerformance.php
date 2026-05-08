<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $quizesHasStudents = Database::search("SELECT COUNT(*) AS count FROM `quiz`
        INNER JOIN `exams` ON `quiz`.`exams_exam_id`=`exams`.`exam_id` 
        INNER JOIN `subjects` ON `exams`.`subjects_subject_id` = `subjects`.`subject_id`
        INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
        INNER JOIN `students_has_intakes` ON `levels`.`intakes_intake_id`=`students_has_intakes`.`intakes_intake_id`
        WHERE `students_has_intakes`.`status_status_id` = '1'");

        $quizesHasStudentsData = $quizesHasStudents->fetch_assoc();

        $quizesSubmitStudents = Database::search("SELECT COUNT(*) AS count FROM `students_has_quiz`");
        $quizesSubmitStudentsData = $quizesSubmitStudents->fetch_assoc();


        $total = $quizesHasStudentsData["count"];
        $submitStudents = (int)$quizesSubmitStudentsData["count"];
        $notSubmitStudents = (int)$total - (int)$submitStudents;

        $labels = ["Submit Students", "Not-Submit Students"];
        $data = [$submitStudents, $notSubmitStudents];

        $status->labels = $labels;
        $status->data = $data;
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo json_encode($status);
