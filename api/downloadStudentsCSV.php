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
    
        $search_text = isset($decoded['text']) ? $decoded['text'] : '';
        $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;

        $query = "SELECT *
                FROM `students`
                INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id` 
                INNER JOIN `status` ON `login_details`.`status_status_id`=`status`.`status_id`
                INNER JOIN `gender` ON `students`.`gender_gender_id`=`gender`.`gender_id`";

        if ($search_text != "") {
            $query .= " WHERE (`first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "' OR `mobile` LIKE '%" . $search_text . "' OR `student_id` LIKE '%" . $search_text . "')";
        }

        if ($sort != 0) {
            if ($sort == 1) {
                $query .= " ORDER BY `first_name` ASC";
            } else if ($sort == 2) {
                $query .= " ORDER BY `first_name` DESC";
            } else if ($sort == 3) {
                if (strpos($query, 'WHERE') == false) {
                    $query .= " WHERE `status_status_id` = 1";
                } else {
                    $query .= " AND `status_status_id` = 1";
                }
            } else if ($sort == 4) {
                if (strpos($query, 'WHERE') == false) {
                    $query .= " WHERE `status_status_id` = 2";
                } else {
                    $query .= " AND `status_status_id` = 2";
                }
            } else if ($sort == 5) {
                $query .= " ORDER BY `student_id` DESC";
            } else if ($sort == 6) {
                $query .= " ORDER BY `student_id` ASC";
            }
        }

        if ($sort == 0 || $sort == 3 || $sort == 4) {
            $query .= " ORDER BY `student_id` DESC";
        }

        $students = Database::search($query);

        if ($students->num_rows > 0) {
            // For CSV download, change headers and output CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="students.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Student Id', 'First Name', 'Last Name', 'Full Name', 'Name with Initials', 'Date of Birth', 'Email', 'Mobile', 'NIC', 'Whatsapp', 'Gender', 'Address Line 1', 'Address Line 2', 'District Id', 'City', 'Postal Code', 'Status']);

            while ($student = $students->fetch_assoc()) {
                $studentRow = [
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
    } else {
        $status->error = 'Invalid Content-Type';
    }
} else {
    $status->error = 'Invalid request method';
}
if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
