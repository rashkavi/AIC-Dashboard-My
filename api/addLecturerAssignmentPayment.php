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
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $timezone = new DateTimeZone("Asia/Colombo");
                    $today = new DateTime("now", $timezone);
                    $todayDate = $today->format('Y-m-d');
                    $nowTime = $today->format('H:i:s');

                    $lecturerId = isset($decoded['lecturerId']) ? $decoded['lecturerId'] : '';
                    $assignmentId = isset($decoded['assignmentId']) ? $decoded['assignmentId'] : '';

                    if (empty($lecturerId) || $lecturerId == 0) {
                        $status->error = 'Lecturer is required';
                    } else {
                        $query = "
                        SELECT 
                            lhsaa.lecturers_lecturers_id AS lecturers_id,
                            
                            COUNT(*) AS total_count,
                            COUNT(CASE WHEN lhsaa.submission_sts_submission_sts_id = 1 THEN 1 END) AS pending_count,
                            COUNT(CASE WHEN lhsaa.submission_sts_submission_sts_id = 2 THEN 1 END) AS marked_count,
                            user_fname,user_lname,title,profile_img,per_assignment_fee
                        FROM 
                            lecturers_has_students_has_assignments AS lhsaa
                        INNER JOIN 
                            lecturers ON lhsaa.lecturers_lecturers_id = lecturers.lecturers_id
                        INNER JOIN 
                            users ON lecturers.users_user_id = users.user_id
                        INNER JOIN 
                            title ON users.title_title_id = title.title_id
                        INNER JOIN 
                            students_has_assignments AS sha ON lhsaa.students_has_assignments_students_has_assignments_id = sha.students_has_assignments_id
                        WHERE 
                            sha.assignments_assignment_id = '" . $assignmentId . "'
                            AND lhsaa.lecturers_lecturers_id = '" . $lecturerId . "'
                        GROUP BY 
                            lhsaa.lecturers_lecturers_id
                        ";

                        $isValidLecture = Database::search($query);

                        if ($isValidLecture->num_rows == 0) {
                            $status->error = 'Lecture not found';
                        } else {
                            $paymentData = $isValidLecture->fetch_assoc();
                            $assignmentPayment = Database::search("SELECT * FROM `assignment_payments` WHERE `lecturers_lecturers_id`='" . $lecturerId . "' AND `assignments_assignment_id`='" . $assignmentId . "' AND `earning_status_earning_status_id`!='2'");

                            $assignmentAmount = $paymentData['marked_count'] * $paymentData['per_assignment_fee'];

                            if ($assignmentPayment->num_rows > 0) {
                                $paymentId = $assignmentPayment->fetch_assoc()['assignment_payments_id'];
                                Database::iud("UPDATE `assignment_payments` SET `amount`='" . $assignmentAmount . "' WHERE `assignment_payments_id`='" . $paymentId . "'");

                                $lecturer_payments_log_columns = "`assignment_payments_assignment_payments_id`, `note`, `date_updated`, `users_user_id`";
                                $lecturer_payments_log_values = "'" . $paymentId . "','Update Assignment Payment', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "'";

                                Database::iud("INSERT INTO `assignment_payments_logs`(" . $lecturer_payments_log_columns . ") VALUES(" . $lecturer_payments_log_values . ")");
                                $status->done = 'Lecturer payment updated successfully';
                            } else {
                                $lecturer_payments_columns = "`lecturers_lecturers_id`, `assignments_assignment_id`, `amount`,`earning_status_earning_status_id`";
                                $lecturer_payments_values = "'" . $lecturerId . "','" . $assignmentId . "','" . $assignmentAmount . "','1'";

                                Database::iud("INSERT INTO `assignment_payments`(" . $lecturer_payments_columns . ") VALUES(" . $lecturer_payments_values . ")");
                                $paymentId = Database::$connection->insert_id;

                                $lecturer_payments_log_columns = "`assignment_payments_assignment_payments_id`, `note`, `date_updated`, `users_user_id`";
                                $lecturer_payments_log_values = "'" . $paymentId . "','Add Lecture Payment', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "'";

                                Database::iud("INSERT INTO `assignment_payments_logs`(" . $lecturer_payments_log_columns . ") VALUES(" . $lecturer_payments_log_values . ")");
                                $status->done = 'Lecturer payment added successfully';
                            }
                        }
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
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
