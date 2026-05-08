<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if (!isset($_POST['assignment_id']) || empty($_POST['assignment_id'])) {
                $status->assignment = "Something went wrong.Please try again";
            } else {
                $assignment_id = $_POST['assignment_id'];
                $isValidId = Database::search("SELECT COUNT(*) as 'count' FROM `assignments` WHERE `assignment_id` = '" . $assignment_id . "'");
                $row = $isValidId->fetch_assoc();
                if ($row['count'] == 0) {
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

                                // Loop through each line of the CSV file
                                while (($line = fgetcsv($csvFile)) !== FALSE) {
                                    if (count(array_filter($line)) != 0) {
                                        $csvData[] = $line; // Add each line to the array
                                    }
                                }
                                fclose($csvFile);
                                if (count($csvData) == 0) {
                                    $status->file = "Please select a valid CSV file";
                                } else if (count($csvData) >= 250) {
                                    $status->file = "Only 250 students can be uploaded at a time";
                                }

                                if (!property_exists($status, 'file')) {
                                    $rowNumber = 1;

                                    $linesErrors = [];

                                    foreach ($csvData as $data) {
                                        $studentHasIntakeId = $data[0];    // 0
                                        $mark = $data[1];        // 1

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
                                            if ($row['count'] == 0) {
                                                $lineErrors[] =  "Row " . $rowNumber . ": Invalid student id";
                                            } else {
                                                $isCheckSubmitAssignment = Database::search("SELECT COUNT(*),`students_has_assignments_id` FROM `students_has_assignments` WHERE `assignments_assignment_id` = '" . $assignment_id . "' AND `students_student_id` = '" . $row['students_student_id'] . "'");
                                                $row = $isCheckSubmitAssignment->fetch_assoc();
                                                if ($row['count'] == 0) {
                                                    $lineErrors[] =  "Row " . $rowNumber . ": Student has not submitted the assignment";
                                                } else {
                                                    $studentAssignmentAssign = Database::search("SELECT * FROM `assignment_recorrection` WHERE `students_has_assignments_students_has_assignments_id`='" . $row['students_has_assignments_id'] . "'");
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
                                            $timezone = new DateTimeZone("Asia/Colombo");
                                            $dateTime = new DateTime("now", $timezone);
                                            $date = $dateTime->format('Y-m-d H:i:s');

                                            $studentHasIntakeId = $data[0];    // 0
                                            $mark = $data[1];        // 1

                                            $student = Database::search("SELECT `students_has_intakes`.`students_student_id`,`students_has_assignments_id` FROM `students_has_intakes`
                                            INNER JOIN `students_has_assignments` ON `students_has_intakes`.`students_student_id`=`students_has_assignments`.`students_student_id`
                                            WHERE `student_id` = '" . $studentHasIntakeId . "' AND `assignments_assignment_id`='" . $assignment_id . "'");
                                            $studentData = $student->fetch_assoc();

                                            Database::iud("UPDATE `assignment_recorrection` SET `marks` = '" . $mark . "' 
                                            WHERE `students_has_assignments_students_has_assignments_id` = '" . $studentData['students_has_assignments_id'] . "'");

                                            $log_insert_columns = "`recorrection_log`,`date_added`,`users_user_id`,`recorrection_recorrection_id`";
                                            $log_insert_values  = "'Update recorrection mark','" . $date . "','" . $sessionUserObject["user_id"] . "','" . $studentData['students_has_assignments_id'] . "'";

                                            Database::iud("INSERT INTO `assignment_recorrection_log` (" . $log_insert_columns . ") VALUES (" . $log_insert_values . ")");
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
