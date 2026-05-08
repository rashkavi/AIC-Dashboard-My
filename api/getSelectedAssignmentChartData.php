<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['assignment']) && !empty($_GET['assignment'])) {
            $assignment = $_GET['assignment'];
            if (empty($assignment) || $assignment == 0) {
                $status->error = 'Invalid assignment';
            } else {
              
                $assignmentsHasStudents = Database::search("SELECT COUNT(*) AS count FROM `assignments` 
                INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
                INNER JOIN `students_has_intakes` ON `levels`.`intakes_intake_id`=`students_has_intakes`.`intakes_intake_id`
                WHERE `assignments`.`assignment_id`='" . $assignment . "' AND `students_has_intakes`.`status_status_id` = '1'");
                $assignmentsHasStudentsData = $assignmentsHasStudents->fetch_assoc();

                $assignmentsSubmitStudents = Database::search("
                SELECT 
                    COUNT(DISTINCT students_student_id) AS count,
                    SUM(CASE WHEN submission_submission_id = 1 THEN 1 ELSE 0 END) AS direct_submit,
                    SUM(CASE WHEN submission_submission_id = 2 THEN 1 ELSE 0 END) AS late_submit,
                    SUM(CASE WHEN submission_submission_id = 3 THEN 1 ELSE 0 END) AS repeat_submit
                FROM (
                    SELECT DISTINCT students_student_id, submission_submission_id
                    FROM students_has_assignments
                    WHERE assignments_assignment_id = '" . $assignment . "'
                ) AS distinct_students
                ");
                $assignmentsSubmitStudentsData = $assignmentsSubmitStudents->fetch_assoc();

                $markSubmitAssignments = Database::search("SELECT COUNT(*) AS count FROM `students_has_assignments`
                INNER JOIN `lecturers_has_students_has_assignments` ON `students_has_assignments`.`students_has_assignments_id`=`lecturers_has_students_has_assignments`.`students_has_assignments_students_has_assignments_id`
                WHERE `students_has_assignments`.`assignments_assignment_id`='".$assignment."' AND `marks` IS NOT NULL");
                $markSubmitAssignmentsData = $markSubmitAssignments->fetch_assoc();

                $markAssignAssignments = Database::search("SELECT COUNT(*) AS count FROM `students_has_assignments`
                INNER JOIN `lecturers_has_students_has_assignments` ON `students_has_assignments`.`students_has_assignments_id`=`lecturers_has_students_has_assignments`.`students_has_assignments_students_has_assignments_id`
                WHERE `students_has_assignments`.`assignments_assignment_id`='".$assignment."' AND `marks` IS NULL");
                $markAssignAssignmentsData = $markAssignAssignments->fetch_assoc();

                $markReleased = Database::search("SELECT COUNT(*) AS count FROM `students_has_assignments` 
                WHERE `students_has_assignments`.`assignments_assignment_id`='".$assignment."' AND
                 (`assignment_marks_status_assignment_marks_status_id` IN (4,5) OR 
                 (`students_has_assignments_id` IN (SELECT `students_has_assignments_students_has_assignments_id` FROM `assignment_recorrection`)))");
                $markReleasedData = $markReleased->fetch_assoc();

                $total = $assignmentsHasStudentsData["count"];
                $submitStudents = (int)$assignmentsSubmitStudentsData["count"];
                $notSubmitStudents = (int)$total - (int)$submitStudents;

                $markSubmit = (int)$markSubmitAssignmentsData["count"];
                $markingAssign = (int) $markAssignAssignmentsData["count"];
                $markRelease = (int)$markReleasedData["count"];
                $pending = (int)$submitStudents - (int)($markSubmit) - (int)$markingAssign;

                $labels2 = ["Pending Assign", "Marking Assigned", "Mark Submit", "Mark Released"];
                $data2 = [$pending, $markingAssign, $markSubmit, $markRelease];

                $labels = ["Not-Submit Students", "Direct Submit Students", "Late Submit Students", "Repeat-Assignment"];
                $data = [$notSubmitStudents, (int)$assignmentsSubmitStudentsData["direct_submit"], (int)$assignmentsSubmitStudentsData["late_submit"], (int)$assignmentsSubmitStudentsData["repeat_submit"]];

                $status->labels = $labels;
                $status->data = $data;
                $status->labels2 = $labels2;
                $status->data2 = $data2;
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
