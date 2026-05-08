<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                    $content = trim(file_get_contents('php://input'));
                    $decoded = json_decode($content, true);
                    if (json_last_error() == JSON_ERROR_NONE) {

                        $payoutsPayment = isset($decoded['payoutsPayment']) ? $decoded['payoutsPayment'] : 0;

                        if (count($payoutsPayment) == 0) {
                            $status->error = "Please select at least one payment";
                        } else {

                            $totalPayableAmount = 0;
                            $totalType1Amount = 0;
                            $totalType2Amount = 0;
                            $totalType3Amount = 0;

                            $assignmentsPayments = Database::search("
                                                                SELECT 
                                                                    s.subject_id,
                                                                    s.subject,
                                                                    s.subject_cover,
                                                                    l.per_assignment_fee,
                                                                    SUM(ap.amount) AS total_assignment_amount,
                                                                    SUM(pd.amount) AS total_paid_amount,
                                                                    l.lecturers_id
                                                                FROM 
                                                                    assignment_payments ap
                                                                INNER JOIN 
                                                                    lecturers l ON ap.lecturers_lecturers_id = l.lecturers_id
                                                                INNER JOIN 
                                                                    subjects s ON l.subjects_subject_id = s.subject_id
                                                                INNER JOIN 
                                                                    users u ON l.users_user_id = u.user_id
                                                                LEFT JOIN 
                                                                    lecturer_payouts lp ON u.user_id = lp.users_user_id AND payout_status_payout_status_id != 3
                                                                LEFT JOIN 
                                                                    payout_data pd ON lp.lecturer_payouts_id = pd.lecturer_payouts_lecturer_payouts_id 
                                                                                  AND pd.payout_type_payout_type_id = 2
                                                                                  AND pd.lecturers_lecturers_id = ap.lecturers_lecturers_id
                                                                WHERE 
                                                                    u.user_id = '" . $sessionUserObject['user_id'] . "'
                                                                GROUP BY 
                                                                    s.subject_id, s.subject
                                                            ");

                            if ($assignmentsPayments->num_rows > 0) {
                                while ($assignmentsPayment = $assignmentsPayments->fetch_assoc()) {
                                    if ($assignmentsPayment['total_assignment_amount'] > $assignmentsPayment['total_paid_amount']) {
                                        $payableAssignmetAmount = $assignmentsPayment['total_assignment_amount'] - $assignmentsPayment['total_paid_amount'];
                                        $assignmentCount = ceil($payableAssignmetAmount / $assignmentsPayment['per_assignment_fee']);
                                        $totalPayableAmount += $payableAssignmetAmount;
                                        $totalType2Amount += $payableAssignmetAmount;
                                    }
                                }
                            }

                            $lecturesPayments = Database::search("
                                                                SELECT 
                                                                    s.subject_id,
                                                                    s.subject,
                                                                    s.subject_cover,
                                                                    s.max_course_hours,
                                                                    l.hourly_rate,
                                                                    SUM(lp.amount) AS total_lectures_amount,
                                                                    SUM(pd.amount) AS total_paid_amount,
                                                                    l.lecturers_id
                                                                FROM 
                                                                    lecturer_payments lp
                                                                INNER JOIN 
                                                                    lecturers l ON lp.lecturers_lecturers_id = l.lecturers_id
                                                                INNER JOIN 
                                                                    subjects s ON l.subjects_subject_id = s.subject_id
                                                                INNER JOIN 
                                                                    users u ON l.users_user_id = u.user_id
                                                                LEFT JOIN 
                                                                    lecturer_payouts lop ON u.user_id = lop.users_user_id AND lop.payout_status_payout_status_id != 3
                                                                LEFT JOIN 
                                                                    payout_data pd ON lop.lecturer_payouts_id = pd.lecturer_payouts_lecturer_payouts_id 
                                                                                 AND pd.payout_type_payout_type_id = 1 
                                                                                 AND pd.lecturers_lecturers_id = lp.lecturers_lecturers_id
                                                                WHERE 
                                                                    u.user_id = '" . $sessionUserObject['user_id'] . "'
                                                                GROUP BY 
                                                                    s.subject_id, s.subject
                                                            ");

                            if ($lecturesPayments->num_rows > 0) {
                                while ($lecturesPayment = $lecturesPayments->fetch_assoc()) {
                                    if ($lecturesPayment['total_lectures_amount'] > $lecturesPayment['total_paid_amount']) {
                                        $completeHours = ceil($lecturesPayment['total_lectures_amount'] / $lecturesPayment['hourly_rate']);
                                        if ($completeHours == $lecturesPayment['max_course_hours'] || $completeHours > $lecturesPayment['max_course_hours']) {
                                            $payableLecturesAmount = $lecturesPayment['total_lectures_amount'] - $lecturesPayment['total_paid_amount'];
                                            $totalPayableAmount += $payableLecturesAmount;
                                            $totalType1Amount += $payableLecturesAmount;
                                        }
                                    }
                                }
                            }

                            $totalPayoutAmount = 0;
                            $totalPayoutAssignmentAmount = 0;
                            $totalPayoutLecturesAmount = 0;
                            foreach ($payoutsPayment as $payment) {
                                $totalPayoutAmount += $payment['amount'];
                            }

                            if ($totalPayoutAmount > $totalPayableAmount) {
                                $status->error = 'Invalid Payout Amount1';
                            } else if ($totalPayoutAssignmentAmount > $totalType2Amount) {
                                $status->error = 'Invalid Payout Amount 2';
                            } else if ($totalPayoutLecturesAmount > $totalType1Amount) {
                                $status->error = 'Invalid Payout Amount 3';
                            } else {
                                $isHasError = false;
                                foreach ($payoutsPayment as $payment) {
                                    if (!$isHasError) {
                                        $lecturer = Database::search("SELECT * FROM `lecturers` WHERE `lecturers_id` = '" . $payment['lecturerId'] . "'");
                                        if ($lecturer->num_rows == 0) {
                                            $isHasError = true;
                                        } else {
                                            $lecturerData = $lecturer->fetch_assoc();
                                            if ($lecturerData['users_user_id'] != $sessionUserObject['user_id']) {
                                                $isHasError = true;
                                            } else if ($payment['type'] == 1) {
                                                $hours = $payment['amount'] / $lecturerData['hourly_rate'];

                                                $fullHours = floor($hours);
                                                $minutes = round(($hours - $fullHours) * 60);

                                                if ($minutes > 0) {
                                                    $formattedHours = "{$fullHours}h {$minutes}m";
                                                } else {
                                                    $formattedHours = "{$fullHours}h";
                                                }
                                                if ($payment['count'] != $formattedHours) {
                                                    $isHasError = true;
                                                }
                                            } else if ($payment['type'] == 2) {
                                                $count = $payment['amount'] / $lecturerData['per_assignment_fee'];
                                                if ($payment['count'] != $count) {
                                                    $isHasError = true;
                                                }
                                            } else {
                                                $isHasError = true;
                                            }
                                        }
                                    }
                                }

                                if ($isHasError) {
                                    $status->error = 'Invalid Payout Amount4';
                                } else {
                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $requestTime = $dateTime->format('Y-m-d H:i:s');

                                    try {
                                        $payout_insert_columns = "`users_user_id`,`amount`,`date`,`payout_status_payout_status_id`";
                                        $payout_insert_values = "'" . $sessionUserObject['user_id'] . "','" . $totalPayoutAmount . "','" . $requestTime . "','1'";

                                        Database::iud("INSERT INTO `lecturer_payouts` (" . $payout_insert_columns . ") VALUES (" . $payout_insert_values . ")");
                                        $lecturerPayoutId = Database::$connection->insert_id;

                                        $payout_log_insert_col = "`lecturer_payouts_lecturer_payouts_id`,`users_user_id`,`date_updated`,`note`";
                                        $payout_log_insert_val = "'" . $lecturerPayoutId . "','" . $sessionUserObject['user_id'] . "','" . $requestTime . "','Payout Requested'";
                                        Database::iud("INSERT INTO `payout_logs` (" . $payout_log_insert_col . ") VALUES (" . $payout_log_insert_val . ")");

                                        foreach ($payoutsPayment as $payment) {
                                            $payout_data_insert_col = "`lecturer_payouts_lecturer_payouts_id`,`lecturers_lecturers_id`,`amount`,`payout_type_payout_type_id`,`payout_data`";
                                            $payout_data_insert_value = "'" . $lecturerPayoutId . "','" . $payment['lecturerId'] . "','" . $payment['amount'] . "','" . $payment['type'] . "','" . $payment['count'] . "'";

                                            Database::iud("INSERT INTO `payout_data` (" . $payout_data_insert_col . ") VALUES (" . $payout_data_insert_value . ")");
                                        }

                                        $status->done = 'Payout requested successfully';
                                    } catch (Exception $ex) {
                                        $status->error = $ex->getMessage();
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
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
