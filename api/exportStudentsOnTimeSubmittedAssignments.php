<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['assignment_id']) && !empty($_GET['assignment_id'])) {

            $assignment_id = $_GET['assignment_id'];
            $isValidAssi = Database::search("SELECT `end_date`,`assignment_code`,`subjects_subject_id` FROM `assignments`
           
            WHERE `assignment_id` = '" . $assignment_id . "'");

            if ($isValidAssi->num_rows == 0) {
                $status->error = 'Invalid assignment ID';
            } else {
                $assignmentData = $isValidAssi->fetch_assoc();
                // Fetch student-submitted assignments for the given assignment ID
                $query = "SELECT `first_name`, `last_name`, `students_has_intakes`.`student_id`, `upload`
                          FROM `students_has_assignments`
                          INNER JOIN `assignments` ON `students_has_assignments`.`assignments_assignment_id`=`assignments`.`assignment_id`
                          INNER JOIN `subjects` ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                          INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                          INNER JOIN `students` ON `students_has_assignments`.`students_student_id` = `students`.`student_id`
                          INNER JOIN `students_has_intakes` ON 
                          (`students_has_intakes`.`students_student_id` = `students`.`student_id` AND `students_has_intakes`.`intakes_intake_id`=`levels`.`intakes_intake_id`)
                          WHERE `assignments_assignment_id` = '" . $assignment_id . "'
                          AND `uploaded_at` < '" . $assignmentData["end_date"] . "'";

                $result = Database::search($query);

                if ($result->num_rows > 0) {
                    $filesToZip = [];
                    while ($submission = $result->fetch_assoc()) {
                        $studentName = $submission['first_name'] . ' ' . $submission['last_name'];
                        $studentId = $submission['student_id'];
                        $filePath = '../assets/uploads/students-assignments/' . $submission['upload']; // Resolve to absolute path

                        if ($filePath === false || !file_exists($filePath) || !is_readable($filePath)) {
                            $status->error = ("File not found or unreadable: " . $submission['upload']);
                            continue; // Skip files that cannot be read
                        }

                        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                        $newFileName = "{$studentName}-{$studentId}.{$extension}";

                        $filesToZip[] = [
                            'path' => $filePath,
                            'new_name' => $newFileName
                        ];
                    }

                    if (count($filesToZip) > 0) {
                        // Create ZIP file
                        $zip = new ZipArchive();
                        $zipFileName = 'On-Time Submitted Assignments - (' . $assignmentData["assignment_code"] . ').zip';

                        if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                            $status->error = 'Failed to create ZIP file';
                        } else {
                            foreach ($filesToZip as $file) {
                                $zip->addFile($file['path'], $file['new_name']);
                            }
                            $zip->close();

                            // Verify ZIP creation success
                            if ($zip->status !== ZipArchive::ER_OK) {
                                $status->error = 'Error creating ZIP file: ' . $zip->getStatusString();
                            } else {
                                // Output ZIP file for download
                                header('Content-Type: application/zip');
                                header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
                                header('Content-Length: ' . filesize($zipFileName));
                                readfile($zipFileName);

                                // Clean up temporary ZIP file
                                unlink($zipFileName);
                                exit;
                            }
                        }
                    } else {
                        $status->error = 'No valid files found to add to ZIP.';
                    }
                } else {
                    $status->error = 'No submissions found for this assignment.';
                }
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method or missing assignment_id';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

// If an error occurred, send JSON response
http_response_code(400);
header('Content-Type: application/json');
echo json_encode($status);
