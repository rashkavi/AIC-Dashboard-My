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
                            user_fname,user_lname,title,profile_img,per_assignment_fee,
                            users.user_id
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
                            lhsaa.lecturers_lecturers_id, user_fname, user_lname, title, profile_img, per_assignment_fee, users.user_id
                        ";

                        $isValidLecture = Database::search($query);

                        if ($isValidLecture->num_rows == 0) {
                            $status->error = 'Lecture not found';
                        } else {
                            $paymentData = $isValidLecture->fetch_assoc();
                            $assignmentPayment = Database::search("SELECT * FROM `assignment_payments` WHERE `lecturers_lecturers_id`='" . $lecturerId . "' AND `assignments_assignment_id`='" . $assignmentId . "' AND `earning_status_earning_status_id`!='2'");

                            // marked assignments of this lecturer for this assignment that are not paid yet (not in a completed payment)
                            $markedAssignments = Database::search("SELECT `lhsaa`.`lecturers_has_students_has_assignments_id` FROM `lecturers_has_students_has_assignments` AS `lhsaa`
                            INNER JOIN `students_has_assignments` AS `sha` ON `lhsaa`.`students_has_assignments_students_has_assignments_id` = `sha`.`students_has_assignments_id`
                            WHERE `lhsaa`.`lecturers_lecturers_id`='" . $lecturerId . "' 
                            AND `sha`.`assignments_assignment_id`='" . $assignmentId . "' 
                            AND `lhsaa`.`submission_sts_submission_sts_id`='2'
                            AND `lhsaa`.`lecturers_has_students_has_assignments_id` NOT IN (
                                SELECT `apma`.`lecturers_has_students_has_assignments_id` FROM `assignment_payments_has_marked_assignments` AS `apma`
                                INNER JOIN `assignment_payments` AS `ap` ON `ap`.`assignment_payments_id` = `apma`.`assignment_payments_id`
                                WHERE `ap`.`earning_status_earning_status_id`='2'
                            )");

                            $assignmentAmount = $markedAssignments->num_rows * $paymentData['per_assignment_fee'];

                            if ($markedAssignments->num_rows == 0) {
                                $status->error = 'No unpaid marked assignments found';
                            } else if ($assignmentPayment->num_rows > 0) {
                                $paymentId = $assignmentPayment->fetch_assoc()['assignment_payments_id'];
                                Database::iud("UPDATE `assignment_payments` SET `amount`='" . $assignmentAmount . "', `earning_status_earning_status_id`='1' WHERE `assignment_payments_id`='" . $paymentId . "'");

                                Database::iud("DELETE FROM `assignment_payments_has_marked_assignments` WHERE `assignment_payments_id`='" . $paymentId . "'");

                                while ($data = $markedAssignments->fetch_assoc()) {
                                    $child_insert = "`assignment_payments_id`,`lecturers_has_students_has_assignments_id`,`date_added`";
                                    $child_values = "'" . $paymentId . "','" . $data['lecturers_has_students_has_assignments_id'] . "','" . $today->format('Y-m-d H:i:s') . "'";
                                    Database::iud("INSERT INTO `assignment_payments_has_marked_assignments`(" . $child_insert . ") VALUES(" . $child_values . ")");
                                }

                                $lecturer_payments_log_columns = "`assignment_payments_assignment_payments_id`, `note`, `date_updated`, `users_user_id`";
                                $lecturer_payments_log_values = "'" . $paymentId . "','Schedule Assignment Payment', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "'";

                                Database::iud("INSERT INTO `assignment_payments_logs`(" . $lecturer_payments_log_columns . ") VALUES(" . $lecturer_payments_log_values . ")");
                                $status->done = 'Lecturer assignment payment scheduled successfully';
                            } else {
                                $lecturer_payments_columns = "`users_user_id`,`lecturers_lecturers_id`, `assignments_assignment_id`, `amount`,`earning_status_earning_status_id`,`date_added`";
                                $lecturer_payments_values = "'" . $paymentData['user_id'] . "','" . $lecturerId . "','" . $assignmentId . "','" . $assignmentAmount . "','1','" . $today->format('Y-m-d H:i:s') . "'";

                                Database::iud("INSERT INTO `assignment_payments`(" . $lecturer_payments_columns . ") VALUES(" . $lecturer_payments_values . ")");
                                $paymentId = Database::$connection->insert_id;

                                while ($data = $markedAssignments->fetch_assoc()) {
                                    $child_insert = "`assignment_payments_id`,`lecturers_has_students_has_assignments_id`,`date_added`";
                                    $child_values = "'" . $paymentId . "','" . $data['lecturers_has_students_has_assignments_id'] . "','" . $today->format('Y-m-d H:i:s') . "'";
                                    Database::iud("INSERT INTO `assignment_payments_has_marked_assignments`(" . $child_insert . ") VALUES(" . $child_values . ")");
                                }

                                $lecturer_payments_log_columns = "`assignment_payments_assignment_payments_id`, `note`, `date_updated`, `users_user_id`";
                                $lecturer_payments_log_values = "'" . $paymentId . "','Schedule Assignment Payment', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "'";

                                Database::iud("INSERT INTO `assignment_payments_logs`(" . $lecturer_payments_log_columns . ") VALUES(" . $lecturer_payments_log_values . ")");
                                $status->done = 'Lecturer assignment payment scheduled successfully';
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
