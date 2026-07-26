<?php
header('Content-Type: application/json'); // Set JSON header at the very start

$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
        $content = trim(file_get_contents('php://input'));
        $decoded = json_decode($content, true);

        if (json_last_error() != JSON_ERROR_NONE) {
            $status->error = 'Invalid JSON';
            http_response_code(400);
            echo json_encode($status);
            exit();
        }

        $intake_id = isset($decoded['intake_id']) ? $decoded['intake_id'] : 0;
        $search_text = isset($decoded['text']) ? $decoded['text'] : '';
        $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;

        if ($intake_id == 0) {
            $status->error = 'Intake ID is required';
        } else {
            $intake = Database::search("SELECT * FROM `intakes` INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `intake_id` = '" . $intake_id . "'");
            if ($intake->num_rows == 0) {
                $status->error = 'Intake not found';
            } else {
                $intakeData = $intake->fetch_assoc();

                $query = "SELECT 
                `shi`.`student_id` AS `studentIntakeId`,
                `s`.`student_id`,
                `ld`.`first_name`,
                `ld`.`last_name`,
                `s`.`full_name`,
                `s`.`name_with_initials`,
                `s`.`date_of_birth`,
                `ld`.`email`,
                `s`.`mobile`,
                `ld`.`whatsapp`,
                `st`.`status`,
                `g`.`gender`,
                `s`.`nic`
            FROM `students_has_intakes` AS `shi`
            INNER JOIN `students` AS `s` 
                ON `shi`.`students_student_id` = `s`.`student_id`
            INNER JOIN `login_details` AS `ld`
                ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
            INNER JOIN `status` AS `st` 
                ON `ld`.`status_status_id` = `st`.`status_id`
            INNER JOIN `gender` AS `g` 
                ON `s`.`gender_gender_id` = `g`.`gender_id`
            WHERE `shi`.`intakes_intake_id` = '" . $intake_id . "'
            ";

                if ($search_text != "") {
                    $query .= " AND (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "' OR `mobile` LIKE '%" . $search_text . "' OR `student_id` LIKE '%" . $search_text . "')";
                }

                if ($sort != 0) {
                    if ($sort == 1) {
                        $query .= " AND `status_status_id` = 1";
                    } else if ($sort == 2) {
                        $query .= " AND `status_status_id` = 2";
                    }
                }

                if ($sort == 0 || $sort == 3 || $sort == 4) {
                    $query .= " ORDER BY `shi`.`student_id` DESC";
                }

                try {

                    $students = Database::search($query);

                    if ($students->num_rows > 0) {
                        // For CSV download, change headers and output CSV
                        $fileName = $intakeData['faculty'] . "-" . $intakeData['programme'] . "-" . $intakeData['intake'] . "-students" . ".csv";
                        header('Content-Type: text/csv');
                        header('Content-Disposition: attachment; filename="' . $fileName . '"');

                        $output = fopen('php://output', 'w');
                        fputcsv($output, ['Student Intake Id', 'Student Id', 'First Name', 'Last Name', 'Full Name (As it appears on the Certificate)', 'Name with Initials', 'Date of Birth', 'Email', 'Mobile', 'NIC', 'Whatsapp', 'Gender', 'Address Line 1', 'Address Line 2', 'District Id', 'City', 'Postal Code', 'Status']);

                        while ($student = $students->fetch_assoc()) {
                            $studentRow = [
                                $student['studentIntakeId'],
                                $student['student_id'],
                                $student['first_name'],
                                $student['last_name'],
                                $student['full_name'],
                                $student['name_with_initials'],
                                $student['date_of_birth'],
                                $student['email'],
                                $student['mobile'],
                                $student['nic'],
                                $student['whatsapp'],
                                $student['gender'],
                                $student['status']
                            ];
                            fputcsv($output, $studentRow);
                        }
                        fclose($output);
                        exit();
                    } else {
                        // Return JSON error if no students found
                        $status->error = 'No students found! Please refresh filters and try again.';
                    }
                } catch (Exception $e) {
                    $status->error = $e->getMessage();
                    http_response_code(500);
                    echo json_encode($status);
                    exit();
                }
            }
        }
    } else {
        $status->error = 'Invalid Content-Type';
    }
} else {
    $status->error = 'Invalid request method';
}
if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
