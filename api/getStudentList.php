<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $students = Database::search("
        SELECT 
            
            title.title,
            first_name,
            last_name,
            email,
            mobile,
            whatsapp,
            profile_img,
            students_has_intakes.student_id,
            programmes.programme,
            nic,
            passport
         
        FROM students_has_intakes
        INNER JOIN students 
            ON students_has_intakes.students_student_id = students.student_id
        INNER JOIN login_details 
            ON students.login_details_login_details_id = login_details.login_details_id
        INNER JOIN title 
            ON students.title_title_id = title.title_id
        INNER JOIN intakes 
            ON students_has_intakes.intakes_intake_id = intakes.intake_id
        INNER JOIN programmes 
            ON intakes.programmes_programme_id = programmes.programme_id
        LEFT JOIN student_has_address
            ON students.student_id = student_has_address.students_student_id
    ");

            $students = $students->fetch_all(MYSQLI_ASSOC);

            $status->students = $students;
            $status->done = 'Students fetched successfully';
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
