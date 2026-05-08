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
            if (!isset($_POST['exam_id']) || empty($_POST['exam_id'])) {
                $status->exam = "Something went wrong.Please try again";
            } else {
                $exam_id = $_POST['exam_id'];
              
                $datetime = new DateTime('now', new DateTimeZone('Asia/Colombo'));
                $formatedDate = $datetime->format('Y-m-d');

                $examination = Database::search("SELECT * FROM `exams` WHERE `exam_id` = '" . $exam_id . "' AND `exam_date` < '" . $formatedDate . "'");

                if ($examination->num_rows == 0) {
                    $status->assignment = "Invalid Exam ID";
                } else {
                    $examinationData = $examination->fetch_assoc();

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('exams', 20,$examinationData['subjects_subject_id']))) {
                        $status->error = 'You are not authorized to filter student exams.';
                    } else {

                        $examType = $examinationData["exam_types_exam_type_id"];

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

                                                if ($examType == 1) {
                                                    $query = "SELECT COUNT(*) as 'count' FROM `students_has_intakes`
                                                INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                                                INNER JOIN `levels` ON `intakes`.`intake_id` = `levels`.`intakes_intake_id`
                                                INNER JOIN `subjects` ON `levels`.`level_id` = `subjects`.`levels_level_id`
                                                INNER JOIN `exams` ON `subjects`.`subject_id` = `exams`.`subjects_subject_id`
                                                INNER JOIN `physical_exam` ON `exams`.`exam_id`=`physical_exam`.`exams_exam_id`
                                                WHERE `exam_id` = '" . $exam_id . "' AND `students_has_intakes`.`student_id` = '" . $studentHasIntakeId . "'";
                                                } elseif ($examType == 2) {
                                                    $query = "SELECT COUNT(*) as 'count' FROM `students_has_intakes`
                                                INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                                                INNER JOIN `levels` ON `intakes`.`intake_id` = `levels`.`intakes_intake_id`
                                                INNER JOIN `subjects` ON `levels`.`level_id` = `subjects`.`levels_level_id`
                                                INNER JOIN `exams` ON `subjects`.`subject_id` = `exams`.`subjects_subject_id`
                                                INNER JOIN `online_exam` ON `exams`.`exam_id`=`online_exam`.`exams_exam_id`
                                                WHERE `exam_id` = '" . $exam_id . "' AND `students_has_intakes`.`student_id` = '" . $studentHasIntakeId . "'";
                                                }

                                                $isValidStudentId = Database::search($query);
                                                $row = $isValidStudentId->fetch_assoc();
                                                if ($row['count'] == 0) {
                                                    $lineErrors[] =  "Row " . $rowNumber . ": Invalid student id";
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
                                                $studentHasIntakeId = $data[0];    // 0
                                                $mark = $data[1];        // 1

                                                $student = Database::search("SELECT `students_student_id` FROM `students_has_intakes` WHERE `student_id` = '" . $studentHasIntakeId . "'");
                                                $data = $student->fetch_assoc();

                                                if ($examType == 1) {
                                                    Database::iud("UPDATE `students_has_physical_exam` SET `exam_status_exam_status_id`='2',`marks`='" . $mark . "' 
                                    WHERE `students_student_id`='" . $data["students_student_id"] . "' AND `physical_exam_physical_exam_id`=(SELECT `physical_exam_id` FROM `physical_exam` WHERE `exams_exam_id`='" . $exam_id . "')");
                                                } else {
                                                    Database::iud("UPDATE `students_has_online_exam` SET `exam_status_exam_status_id`='2',`marks`='" . $mark . "' 
                                                WHERE `students_student_id`='" . $data["students_student_id"] . "' AND `online_exam_online_exam_id`=(SELECT `online_exam_id` FROM `online_exam` WHERE `exams_exam_id`='" . $exam_id . "')");
                                                }
                                            }
                                            $status->done = "Marks uploaded successfully";
                                        }
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
