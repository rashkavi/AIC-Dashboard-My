<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['condition']) && !empty($_GET['condition'])) {
            $condition = isset($_GET['condition']) ? $_GET['condition'] : 1;
            if (empty($condition) || $condition < 0 || $condition > 3) {
                $status->error = 'Invalid condition';
            } else {
                switch ($condition) {
                    case 1: // Weekly Process
                        $label = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        $data = array_fill(0, 7, 0);

                        $startOfWeek = new DateTime('monday this week');
                        $endOfWeek = (clone $startOfWeek)->modify('+6 days'); // Sunday of the same week

                        // Batch query to get all engagement counts for the week
                        $startDateTime = $startOfWeek->format('Y-m-d 00:00:00');
                        $endDateTime = $endOfWeek->format('Y-m-d 23:59:59');
                        $result = Database::search("
                            SELECT DATE(`date_paid`) AS day, SUM(`amount`) AS total 
                            FROM `course_payments` 
                            INNER JOIN `course_payment_has_payments` ON `course_payments_id` = `course_payments_course_payments_id`
                            WHERE `date_paid` BETWEEN '$startDateTime' AND '$endDateTime'
                            GROUP BY day
                        ");

                        $engagementData = [];
                        while ($row = $result->fetch_assoc()) {
                            $engagementData[$row['day']] = $row['total'];
                        }

                        for ($i = 0; $i < 7; $i++) {
                            $currentDate = $startOfWeek->format('Y-m-d');
                            $data[$i] = $engagementData[$currentDate] ?? 0; // Use 0 if no data for the day
                            $startOfWeek->modify('+1 day');
                        }

                        $status->labels = $label;
                        $status->data = $data;
                        break;

                    case 2: // Monthly Process
                        $label = [];
                        $data = [];

                        $startOfMonth = new DateTime('first day of this month');
                        $endOfMonth = new DateTime('last day of this month');

                        $startDateTime = $startOfMonth->format('Y-m-d 00:00:00');
                        $endDateTime = $endOfMonth->format('Y-m-d 23:59:59');

                        // Batch query to get engagement counts for all days of the month
                        $result = Database::search("
                        SELECT DATE(`date_paid`) AS day, SUM(`amount`) AS total 
                        FROM `course_payments` 
                         INNER JOIN `course_payment_has_payments` ON `course_payments_id` = `course_payments_course_payments_id`
                        WHERE `date_paid` BETWEEN '$startDateTime' AND '$endDateTime'
                        GROUP BY day
                    ");

                        $engagementData = [];
                        while ($row = $result->fetch_assoc()) {
                            $engagementData[$row['day']] = $row['total'];
                        }

                        $currentDay = clone $startOfMonth;
                        while ($currentDay <= $endOfMonth) {
                            $currentDate = $currentDay->format('Y-m-d');
                            $label[] = $currentDay->format('d'); // Day of the month
                            $data[] = $engagementData[$currentDate] ?? 0; // Use 0 if no data for the day
                            $currentDay->modify('+1 day');
                        }

                        $status->labels = $label;
                        $status->data = $data;
                        break;

                    case 3: // Yearly Process
                        $label = [];
                        $data = [];

                        $currentYear = date('Y');
                        $startOfYear = new DateTime("$currentYear-01-01 00:00:00");
                        $endOfYear = new DateTime("$currentYear-12-31 23:59:59");

                        // Batch query to get engagement counts for all months of the year
                        $result = Database::search("
                        SELECT MONTH(`date_paid`) AS month, SUM(`amount`) AS total 
                        FROM `course_payments` 
                         INNER JOIN `course_payment_has_payments` ON `course_payments_id` = `course_payments_course_payments_id`
                        WHERE `date_paid` BETWEEN '{$startOfYear->format('Y-m-d H:i:s')}' AND '{$endOfYear->format('Y-m-d H:i:s')}'
                        GROUP BY month
                    ");

                        $engagementData = [];
                        while ($row = $result->fetch_assoc()) {
                            $engagementData[$row['month']] = $row['total'];
                        }

                        for ($month = 1; $month <= 12; $month++) {
                            $label[] = date('F', mktime(0, 0, 0, $month, 1)); // Month name
                            $data[] = $engagementData[$month] ?? 0; // Use 0 if no data for the month
                        }

                        $status->labels = $label;
                        $status->data = $data;
                        break;
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
