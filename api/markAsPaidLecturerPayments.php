<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $userId = isset($decoded['userId']) ? $decoded['userId'] : 0;
                    $paidAmount = isset($decoded['paidAmount']) ? $decoded['paidAmount'] : 0;
                    $lecturePayments = isset($decoded['lecturePayments']) ? $decoded['lecturePayments'] : [];
                    $assignmentPayments = isset($decoded['assignmentPayments']) ? $decoded['assignmentPayments'] : [];
                    $paidDate = isset($decoded['paidDate']) ? $decoded['paidDate'] : 0;
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

                    if (empty($userId) || $userId == 0) {
                        $status->error = "Invalid payment id";
                    } else {
                        $query = "SELECT 
                        u.*,
                        t.title,
                        lp.total_lecture_payments,
                        ap.total_assignments_payments
                    FROM users u
                    INNER JOIN title t 
                        ON u.title_title_id = t.title_id
                    LEFT JOIN (
                        SELECT users_user_id, SUM(amount) AS total_lecture_payments
                        FROM lecturer_payments
                        WHERE earning_status_earning_status_id = '1'
                        GROUP BY users_user_id
                    ) lp ON lp.users_user_id = u.user_id
            
                    LEFT JOIN (
                        SELECT users_user_id, SUM(amount) AS total_assignments_payments
                        FROM assignment_payments
                        WHERE earning_status_earning_status_id = '1'
                        GROUP BY users_user_id
                    ) ap ON ap.users_user_id = u.user_id
            
                    WHERE u.user_id = '" . $userId . "' AND lp.users_user_id IS NOT NULL 
                    OR ap.users_user_id IS NOT NULL
                    GROUP BY u.user_id";

                        $result = Database::search($query);
                        if ($result->num_rows == 0) {
                            $status->error = "Invalid payment id";
                        } else {
                            $lecturerData = $result->fetch_assoc();

                            if (count($lecturePayments) == 0 && count($assignmentPayments) == 0) {
                                $status->error = "No payments to mark as paid";
                            } else {

                                if (count($lecturePayments) > 0) {
                                    $isValidLecturePayments = Database::search("SELECT `lecturer_payments_id` FROM `lecturer_payments` 
                                WHERE `lecturer_payments_id` IN (" . implode(",", $lecturePayments) . ") 
                                AND `earning_status_earning_status_id` = '1'
                                AND `users_user_id` = '" . $userId . "'");

                                    if ($isValidLecturePayments->num_rows == 0) {
                                        $status->error = "Invalid payments1";
                                    } else if (count($lecturePayments) != $isValidLecturePayments->num_rows) {
                                        $status->error = "Invalid payments2";
                                    }
                                }

                                if (count($assignmentPayments) > 0) {
                                    $isValidAssignmentPayments = Database::search("SELECT `assignment_payments_id` FROM `assignment_payments` 
                                WHERE `assignment_payments_id` IN (" . implode(",", $assignmentPayments) . ") 
                                AND `earning_status_earning_status_id` = '1'
                                AND `users_user_id` = '" . $userId . "'");

                                    if ($isValidAssignmentPayments->num_rows == 0) {
                                        $status->error = "Invalid payments3";
                                    } else if (count($assignmentPayments) != $isValidAssignmentPayments->num_rows) {
                                        $status->error = "Invalid payments4";
                                    }
                                }

                                if (!property_exists($status, 'error')) {

                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $today = new DateTime("now", $timezone);

                                    $insert_col = "`users_user_id`,`date_paid`,`amount`,`paid_by`,`date_marked`";
                                    $insert_val = "'" . $userId . "',
                                        '" . $paidDate . "',
                                        '" . $paidAmount . "',
                                        '" . $sessionUserObject['user_id'] . "',
                                        '" . $today->format('Y-m-d H:i:s') . "'
                                        ";

                                    if (!empty($note)) {
                                        $insert_col .= ",`note`";
                                        $insert_val .= ",'" . Database::escape($note) . "'";
                                    }

                                    Database::search("INSERT INTO `lecturers_paid_payments` (" . $insert_col . ") VALUES (" . $insert_val . ")");
                                    $paymentId = Database::$connection->insert_id;

                                    if (count($lecturePayments) > 0) {

                                        Database::iud("UPDATE `lecturer_payments` SET `earning_status_earning_status_id` = '2',
                                    `lecturers_paid_payments_lecturers_paid_payment_id`='" . $paymentId . "'
                                    WHERE `lecturer_payments_id` IN (" . implode(",", $lecturePayments) . ")");

                                        foreach ($lecturePayments as $payment) {
                                            Database::iud("INSERT INTO `lecturer_payments_logs` (`lecturer_payments_lecturer_payments_id`, `note`, `date_updated`, `users_user_id`,`log`) VALUES
                                    ('" . $payment . "', '" . Database::escape($note) . "', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "','Marked as paid')");
                                        }
                                    }

                                    if (count($assignmentPayments) > 0) {
                                        Database::iud("UPDATE `assignment_payments` SET `earning_status_earning_status_id` = '2',
                                    `lecturers_paid_payments_lecturers_paid_payment_id`='" . $paymentId . "'
                                    WHERE `assignment_payments_id` IN (" . implode(",", $assignmentPayments) . ")");

                                        foreach ($assignmentPayments as $payment) {
                                            Database::iud("INSERT INTO `assignment_payments_logs` (`assignment_payments_assignment_payments_id`, `note`, `date_updated`, `users_user_id`,`log`) VALUES
                                    ('" . $payment . "', '" . Database::escape($note) . "', '" . $today->format('Y-m-d H:i:s') . "', '" . $sessionUserObject['user_id'] . "','Marked as paid')");
                                        }
                                    }

                                    $status->done = 'Collected lecturer payment marked as paid successfully';
                                }
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
