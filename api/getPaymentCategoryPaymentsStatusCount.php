<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['category']) && !empty($_GET['category'])) {
            $category = $_GET['category'];
            if (empty($category) || $category == 0) {
                $status->error = 'Invalid category';
            } else {

                $query = "SELECT 
                    ps.`payment_status_id`,
                    ps.`payment_status`,
                    COUNT(cp.`course_payment_has_payment_id`) AS payment_count
                FROM `payment_status` ps
                LEFT JOIN (
                    SELECT 
                        cpp.`payment_status_payment_status_id`,
                        cpp.`course_payment_has_payment_id`
                    FROM `course_payment_has_payments` cpp
                    JOIN `course_payments` cp
                        ON cpp.`course_payments_course_payments_id` = cp.`course_payments_id`
                    JOIN `payment_plan_has_students_has_intakes` pphsi
                        ON cp.`payment_plan_has_students_has_intakes_id` = pphsi.`payment_plan_has_students_has_intakes_id`
                    WHERE pphsi.`payment_category_payment_category_id` = '" . $category . "'
                ) cp ON ps.`payment_status_id` = cp.`payment_status_payment_status_id`
                GROUP BY ps.`payment_status_id`, ps.`payment_status`
                ORDER BY payment_status_id ASC";

                $paymentStatusHasStudents = Database::search($query, [$category]);

                $labels = [];
                $data = [];

                if ($paymentStatusHasStudents) {
                    while ($row = $paymentStatusHasStudents->fetch_assoc()) {
                        $labels[] = $row['payment_status'];
                        $data[] = (int)$row['payment_count']; // Convert to integer
                    }

                    $status->success = true;
                    $status->labels = $labels;
                    $status->data = $data;
                } else {
                    $status->error = 'Database query failed';
                }
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method or missing category parameter';
        }
    } else {
        http_response_code(401);
        $status->error = 'Authentication Failed!';
    }
} else {
    http_response_code(401);
    $status->error = 'Authentication Failed!';
}

header('Content-Type: application/json');
echo json_encode($status);
