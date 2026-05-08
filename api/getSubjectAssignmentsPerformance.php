<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['subject']) && !empty($_GET['subject'])) {
            $subject = isset($_GET['subject']) ? $_GET['subject'] : 1;
            if (empty($subject) || $subject == 0) {
                $status->error = 'Invalid subject';
            } else {
              
                $subjectHasAssignments = Database::search("SELECT `subject_type_subject_type_id`,`subject_id` FROM `assignments` 
                INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                WHERE `subjects`.`subject_id`='" . $subject . "'");

                if ($subjectHasAssignments->num_rows == 0) {
                    $labels = ["Submit Students", "Not-Submit Students", "Marking", "Marks Released"];
                    $data = [0, 0, 0, 0];
                } else {
                    $assignmentsHasStudents = 0;
                    while ($data = $subjectHasAssignments->fetch_assoc()) {
                        if ($data['subject_type_subject_type_id'] != 1) {
                            $countQuery = Database::search("SELECT COUNT(*) AS count FROM `students_has_subjects`
                                          WHERE `subjects_subject_id` = '" . $data['subject_id'] . "'");
                            $countResult = $countQuery->fetch_assoc();
                            $assignmentsHasStudents += (int)$countResult['count'];
                        } else {
                            $countQuery = Database::search("SELECT COUNT(*) AS count FROM `students_has_intakes`
                                          WHERE `intakes_intake_id` = (SELECT `intakes_intake_id` FROM `levels` 
                                          WHERE `level_id`='" . $subject . "')");
                            $countResult = $countQuery->fetch_assoc();
                            $assignmentsHasStudents += (int)$countResult['count'];
                        }
                    }

                    $assignmentsSubmitStudents = Database::search("SELECT COUNT(*) AS count
                    FROM `students_has_assignments`
                    INNER JOIN `assignments` ON `students_has_assignments`.`assignments_assignment_id`=`assignments`.`assignment_id`
                    INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                    WHERE `subjects`.`subject_id`='" . $subject . "'");
                    $assignmentsSubmitStudentsData = $assignmentsSubmitStudents->fetch_assoc();

                    $markReleasedAssignments = Database::search("SELECT COUNT(*) AS count FROM `students_has_assignments`
                    INNER JOIN `lecturers_has_students_has_assignments` ON `students_has_assignments`.`students_has_assignments_id`=`lecturers_has_students_has_assignments`.`students_has_assignments_students_has_assignments_id`
                    INNER JOIN `assignments` ON `students_has_assignments`.`assignments_assignment_id`=`assignments`.`assignment_id`
                    INNER JOIN `subjects` ON `assignments`.`subjects_subject_id`=`subjects`.`subject_id`
                    WHERE `subjects`.`subject_id`='" . $subject . "'
                    AND `marks` IS NOT NULL");
                    $markReleasedAssignmentsData = $markReleasedAssignments->fetch_assoc();

                    $total = $assignmentsHasStudents;
                    $submitStudents = (int)$assignmentsSubmitStudentsData["count"];
                    $notSubmitStudents = (int)$total - (int)$submitStudents;
                    $markReleased = (int)$markReleasedAssignmentsData["count"];
                    $marking = (int)$submitStudents - (int)($markReleased);

                    $labels = ["Submit Students", "Not-Submit Students", "Marking", "Marks Released"];
                    $data = [$submitStudents, $notSubmitStudents, $marking, $markReleased];
                }

                $status->labels = $labels;
                $status->data = $data;
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
