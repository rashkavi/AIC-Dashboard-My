<?php
$status = new stdClass();
require_once '../includes/UserClass.php';
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (!isset($_POST['assignment_id']) || empty($_POST['assignment_id'])) {
                $status->assignment = "Something went wrong.Please try again";
            } else {
                $assignment_id = $_POST['assignment_id'];
                $isValidId = Database::search("SELECT COUNT(*) FROM `assignments` WHERE `assignment_id` = '" . $assignment_id . "'");
                $row = $isValidId->fetch_assoc();
                if ($row["COUNT(*)"] == 0) {
                    $status->assignment = "Invalid Assignment ID";
                } else {
                    if (!isset($_FILES["file"])) {
                        $status->file = "Please select a csv file";
                    } else {
                        $csvMimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain');
                        if (empty($_FILES['file']['name']) && !in_array($_FILES['file']['type'], $csvMimes)) {
                            $status->file = "Please select a valid CSV file";
                        } else {
                            if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
                                $status->file = "Please select a valid CSV file";
                            } else {
                                $csvFile = fopen($_FILES['file']['tmp_name'], 'r');
                                fgetcsv($csvFile);

                                $approvedStudebtCount = 0;

                                $csvData = [];

                                while (($line = fgetcsv($csvFile)) !== FALSE) {
                                    if (count(array_filter($line)) != 0) {
                                        $csvData[] = $line;
                                    }
                                }
                                fclose($csvFile);
                                if (count($csvData) == 0) {
                                    $status->file = "Please select a valid CSV file";
                                }

                                if (!property_exists($status, 'file')) {
                                    $rowNumber = 1;

                                    $linesErrors = [];

                                    foreach ($csvData as $data) {
                                        $studentHasIntakeId = $data[0];   
                                        $mark = $data[1];       

                                        $lineErrors = [];

                                        if (empty($studentHasIntakeId)) {
                                            $lineErrors[] =  "Row " . $rowNumber . ": student id cannot be empty";
                                        } else if (strlen($studentHasIntakeId) > 45) {
                                            $lineErrors[] =  "Row " . $rowNumber . ": Student ID should be less than 45 characters";
                                        } else {
                                            $query = "SELECT COUNT(*), `students_student_id` FROM `students_has_intakes`
                                            INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                                            INNER JOIN `levels` ON `intakes`.`intake_id` = `levels`.`intakes_intake_id`
                                            INNER JOIN `subjects` ON `levels`.`level_id` = `subjects`.`levels_level_id`
                                            INNER JOIN `assignments` ON `subjects`.`subject_id` = `assignments`.`subjects_subject_id`
                                            WHERE `assignment_id` = '" . $assignment_id . "' AND `students_has_intakes`.`student_id` = '" . $studentHasIntakeId . "'";

                                            $isValidStudentId = Database::search($query);
                                            $row = $isValidStudentId->fetch_assoc();
                                            if ($row['COUNT(*)'] == 0) {
                                                $lineErrors[] =  "Row " . $rowNumber . ": Invalid student id";
                                            } else {
                                                $isCheckSubmitAssignment = Database::search("SELECT COUNT(*),`students_has_assignments_id` FROM `students_has_assignments` WHERE `assignments_assignment_id` = '" . $assignment_id . "' AND `students_student_id` = '" . $row['students_student_id'] . "'");
                                                $row = $isCheckSubmitAssignment->fetch_assoc();
                                                if ($row['COUNT(*)'] == 0) {
                                                    $lineErrors[] =  "Row " . $rowNumber . ": Student has not submitted the assignment";
                                                } else {
                                                    $studentAssignmentAssign = Database::search("SELECT * FROM `lecturers_has_students_has_assignments` WHERE `students_has_assignments_students_has_assignments_id`='" . $row['students_has_assignments_id'] . "'");
                                                    if ($studentAssignmentAssign->num_rows == 0) {
                                                        $lineErrors[] =  "Row " . $rowNumber . ": No lecturer assigned to the student's assignment.";
                                                    }
                                                }
                                            }
                                        }

                                        if (empty($mark)) {
                                            $lineErrors[] =  "Row " . $rowNumber . ": Mark cannot be empty";
                                        } else if (!preg_match('/^[0-9]{1,3}$/', $mark)) {
                                            $lineErrors[] =  "Row " . $rowNumber . ": Please enter a valid mark";
                                        } else if ($mark < 0 || $mark > 100) {
                                            $lineErrors[] =  "Row " . $rowNumber . ": Please enter a valid mark";
                                        }

                                        $rowNumber++;
                                        if (count($lineErrors) > 0) {
                                            $linesErrors[] = $lineErrors;
                                        }
                                    }
                                    if (!empty($linesErrors)) {
                                        $status->dataErrors = $linesErrors;
                                    } else {

                                        foreach ($csvData as $data) {
                                            $studentHasIntakeId = $data[0];
                                            $mark = $data[1];

                                            $student = Database::search("SELECT `students_student_id` FROM `students_has_intakes` WHERE `student_id` = '" . $studentHasIntakeId . "'");
                                            $studentData = $student->fetch_assoc();

                                            Database::iud("UPDATE `lecturers_has_students_has_assignments` SET `marks` = '" . $mark . "' 
                                            WHERE `students_has_assignments_students_has_assignments_id` = (SELECT `students_has_assignments_id` FROM `students_has_assignments` WHERE `students_student_id`='" . $studentData['students_student_id'] . "' AND `assignments_assignment_id`='" . $assignment_id . "')");

                                            Database::iud("UPDATE `students_has_assignments` SET `assignment_marks_status_assignment_marks_status_id`='3' 
                                            WHERE `students_student_id`='" . $studentData['students_student_id'] . "' AND `assignments_assignment_id`='" . $assignment_id . "'");
                                        }
                                        $status->done = "Marks uploaded successfully";
                                    }
                                }
                            }
                        }
                    }
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
