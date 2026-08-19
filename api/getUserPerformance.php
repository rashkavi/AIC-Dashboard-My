<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $id = isset($decoded['id']) ? $decoded['id'] : '';
                    $condition = isset($decoded['condition']) ? $decoded['condition'] : 1;
                    if (empty($id)) {
                        $status->error = "Invalid user Id";
                    } else if (!is_numeric($id)) {
                        $status->error = "Invalid user Id";
                    } else {
                        $isValidUser = Database::search("SELECT COUNT(*) as 'count' FROM `users` WHERE `user_id`='" . $id . "'");
                        $row = $isValidUser->fetch_assoc();
                        if ($row['count'] == 0) {
                            $status->error = "Invalid user Id";
                        } else {
                            if (empty($condition) || $condition < 0 || $condition > 3) {
                                $status->error = 'Invalid condition';
                            } else {

                                $startDateTime = '';
                                $endDateTime = '';

                                switch ($condition) {
                                    case 1:
                                        $startOfWeek = new DateTime('monday this week');
                                        $endOfWeek = (clone $startOfWeek)->modify('+6 days');

                                        $startDateTime = $startOfWeek->format('Y-m-d 00:00:00');
                                        $endDateTime = $endOfWeek->format('Y-m-d 23:59:59');
                                        break;
                                    case 2:

                                        $startOfMonth = new DateTime('first day of this month');
                                        $endOfMonth = new DateTime('last day of this month');

                                        $startDateTime = $startOfMonth->format('Y-m-d 00:00:00');
                                        $endDateTime = $endOfMonth->format('Y-m-d 23:59:59');

                                        break;
                                    case 3:
                                        $currentYear = date('Y');
                                        $startOfYear = new DateTime("$currentYear-01-01 00:00:00");
                                        $endOfYear = new DateTime("$currentYear-12-31 23:59:59");

                                        $startDateTime = $startOfYear->format('Y-m-d 00:00:00');
                                        $endDateTime = $endOfYear->format('Y-m-d 23:59:59');
                                }

                                // Bucketed lead_student_status_id groups (agreed grouping)
                                // Pending: 1,6,7,11 | Rejected: 2,9,12 | In Progress: 3,4,5,8,10,13
                                // Ready: 14,15,16 | Enrolled: 17
                                $studentCountQuery = "
                                SELECT 
                                    DATE(`x`.`date_updated`) AS `day`,
                                    MONTH(`x`.`date_updated`) AS `month`,

                                    SUM(CASE 
                                        WHEN `x`.`lead_student_status_lead_student_status_id` IN (1,6,7,11) THEN 1 
                                        ELSE 0 
                                    END) AS pending_count,

                                    SUM(CASE 
                                        WHEN `x`.`lead_student_status_lead_student_status_id` IN (2,9,12) THEN 1 
                                        ELSE 0 
                                    END) AS rejected_count,

                                    SUM(CASE 
                                        WHEN `x`.`lead_student_status_lead_student_status_id` IN (3,4,5,8,10,13) THEN 1 
                                        ELSE 0 
                                    END) AS inprogress_count,

                                    SUM(CASE 
                                        WHEN `x`.`lead_student_status_lead_student_status_id` IN (14,15,16) THEN 1 
                                        ELSE 0 
                                    END) AS ready_count,

                                    SUM(CASE 
                                        WHEN `x`.`lead_student_status_lead_student_status_id` = 17 THEN 1 
                                        ELSE 0 
                                    END) AS enrolled_count

                                FROM (
                                    SELECT 
                                        `sl1`.`students_student_id`,
                                        `sl1`.`lead_student_status_lead_student_status_id`,
                                        `sl1`.`date_updated`
                                    FROM `students_logs` AS `sl1`
                                    INNER JOIN (
                                        SELECT 
                                            `students_student_id`,
                                            MAX(`students_log_id`) AS `max_log_id`
                                        FROM `students_logs`
                                        WHERE 
                                            `users_user_id` = '" . $id . "'
                                            AND `date_updated` BETWEEN '" . $startDateTime . "' AND '" . $endDateTime . "'
                                        GROUP BY `students_student_id`
                                    ) AS `latest`
                                        ON `latest`.`max_log_id` = `sl1`.`students_log_id`
                                ) AS `x`
                                GROUP BY DATE(`x`.`date_updated`)
                                ORDER BY DATE(`x`.`date_updated`) ASC
                            ";

                                switch ($condition) {
                                    case 1: // Weekly Process
                                        $label = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

                                        $pendingData = array_fill(0, 7, 0);
                                        $rejectedData = array_fill(0, 7, 0);
                                        $inProgressData = array_fill(0, 7, 0);
                                        $readyData = array_fill(0, 7, 0);
                                        $enrolledData = array_fill(0, 7, 0);

                                        $result = Database::search($studentCountQuery);

                                        $engagementData = [];
                                        while ($row = $result->fetch_assoc()) {
                                            $engagementData[$row['day']] = [
                                                'pending' => (int)$row['pending_count'],
                                                'rejected' => (int)$row['rejected_count'],
                                                'inprogress' => (int)$row['inprogress_count'],
                                                'ready' => (int)$row['ready_count'],
                                                'enrolled' => (int)$row['enrolled_count'],
                                            ];
                                        }

                                        $currentDay = new DateTime('monday this week');
                                        for ($i = 0; $i < 7; $i++) {
                                            $dayKey = $currentDay->format('Y-m-d');

                                            $pendingData[$i] = $engagementData[$dayKey]['pending'] ?? 0;
                                            $rejectedData[$i] = $engagementData[$dayKey]['rejected'] ?? 0;
                                            $inProgressData[$i] = $engagementData[$dayKey]['inprogress'] ?? 0;
                                            $readyData[$i] = $engagementData[$dayKey]['ready'] ?? 0;
                                            $enrolledData[$i] = $engagementData[$dayKey]['enrolled'] ?? 0;

                                            $currentDay->modify('+1 day');
                                        }

                                        $status->labels = $label;
                                        $status->pending = $pendingData;
                                        $status->rejected = $rejectedData;
                                        $status->inprogress = $inProgressData;
                                        $status->ready = $readyData;
                                        $status->enrolled = $enrolledData;
                                        break;

                                    case 2: // Monthly Process
                                        $label = [];

                                        $pendingData = [];
                                        $rejectedData = [];
                                        $inProgressData = [];
                                        $readyData = [];
                                        $enrolledData = [];

                                        $result = Database::search($studentCountQuery);

                                        $engagementData = [];
                                        while ($row = $result->fetch_assoc()) {
                                            $engagementData[$row['day']] = [
                                                'pending' => (int)$row['pending_count'],
                                                'rejected' => (int)$row['rejected_count'],
                                                'inprogress' => (int)$row['inprogress_count'],
                                                'ready' => (int)$row['ready_count'],
                                                'enrolled' => (int)$row['enrolled_count'],
                                            ];
                                        }

                                        $currentDay = clone $startOfMonth;
                                        while ($currentDay <= $endOfMonth) {
                                            $currentDate = $currentDay->format('Y-m-d');

                                            $label[] = $currentDay->format('d');
                                            $pendingData[] = $engagementData[$currentDate]['pending'] ?? 0;
                                            $rejectedData[] = $engagementData[$currentDate]['rejected'] ?? 0;
                                            $inProgressData[] = $engagementData[$currentDate]['inprogress'] ?? 0;
                                            $readyData[] = $engagementData[$currentDate]['ready'] ?? 0;
                                            $enrolledData[] = $engagementData[$currentDate]['enrolled'] ?? 0;

                                            $currentDay->modify('+1 day');
                                        }

                                        $status->labels = $label;
                                        $status->pending = $pendingData;
                                        $status->rejected = $rejectedData;
                                        $status->inprogress = $inProgressData;
                                        $status->ready = $readyData;
                                        $status->enrolled = $enrolledData;
                                        break;

                                    case 3: // Yearly Process
                                        $label = [];

                                        $pendingData = array_fill(0, 12, 0);
                                        $rejectedData = array_fill(0, 12, 0);
                                        $inProgressData = array_fill(0, 12, 0);
                                        $readyData = array_fill(0, 12, 0);
                                        $enrolledData = array_fill(0, 12, 0);

                                        $result = Database::search($studentCountQuery);

                                        $engagementData = [];
                                        while ($row = $result->fetch_assoc()) {
                                            $month = (int)$row['month'];

                                            if (!isset($engagementData[$month])) {
                                                $engagementData[$month] = [
                                                    'pending' => 0,
                                                    'rejected' => 0,
                                                    'inprogress' => 0,
                                                    'ready' => 0,
                                                    'enrolled' => 0,
                                                ];
                                            }

                                            $engagementData[$month]['pending'] += (int)$row['pending_count'];
                                            $engagementData[$month]['rejected'] += (int)$row['rejected_count'];
                                            $engagementData[$month]['inprogress'] += (int)$row['inprogress_count'];
                                            $engagementData[$month]['ready'] += (int)$row['ready_count'];
                                            $engagementData[$month]['enrolled'] += (int)$row['enrolled_count'];
                                        }

                                        for ($month = 1; $month <= 12; $month++) {
                                            $label[] = date('F', mktime(0, 0, 0, $month, 1));

                                            $pendingData[$month - 1] = $engagementData[$month]['pending'] ?? 0;
                                            $rejectedData[$month - 1] = $engagementData[$month]['rejected'] ?? 0;
                                            $inProgressData[$month - 1] = $engagementData[$month]['inprogress'] ?? 0;
                                            $readyData[$month - 1] = $engagementData[$month]['ready'] ?? 0;
                                            $enrolledData[$month - 1] = $engagementData[$month]['enrolled'] ?? 0;
                                        }

                                        $status->labels = $label;
                                        $status->pending = $pendingData;
                                        $status->rejected = $rejectedData;
                                        $status->inprogress = $inProgressData;
                                        $status->ready = $readyData;
                                        $status->enrolled = $enrolledData;
                                        break;
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
