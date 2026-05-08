<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        
        $assignmentsHasStudents = Database::search("SELECT COUNT(*) AS count FROM `assignments` 
        INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
        INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
        INNER JOIN `students_has_intakes` ON `levels`.`intakes_intake_id`=`students_has_intakes`.`intakes_intake_id`
        WHERE `students_has_intakes`.`status_status_id` = '1'");
        $assignmentsHasStudentsData = $assignmentsHasStudents->fetch_assoc();

        $assignmentsSubmitStudents = Database::search("SELECT COUNT(*) AS count FROM `students_has_assignments`");
        $assignmentsSubmitStudentsData = $assignmentsSubmitStudents->fetch_assoc();

        $markReleasedAssignments = Database::search("SELECT COUNT(*) AS count FROM `students_has_assignments`
        INNER JOIN `lecturers_has_students_has_assignments` ON `students_has_assignments`.`students_has_assignments_id`=`lecturers_has_students_has_assignments`.`students_has_assignments_students_has_assignments_id`
         WHERE `marks` IS NOT NULL");
        $markReleasedAssignmentsData = $markReleasedAssignments->fetch_assoc();

        $total = $assignmentsHasStudentsData["count"];
        $submitStudents = (int)$assignmentsSubmitStudentsData["count"];
        $notSubmitStudents = (int)$total - (int)$submitStudents;
        $markReleased = (int)$markReleasedAssignmentsData["count"];
        $marking = (int)$submitStudents - (int)($markReleased);

        $labels = ["Submit Students", "Not-Submit Students", "Marking", "Marks Released"];
        $data = [$submitStudents, $notSubmitStudents, $marking, $markReleased];

        $status->labels = $labels;
        $status->data = $data;
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo json_encode($status);
