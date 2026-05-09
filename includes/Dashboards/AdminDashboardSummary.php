<?php

class AdminDashboardSummary
{
    private static function fetchRow($sql)
    {
        $result = Database::search($sql);
        return $result ? $result->fetch_assoc() : [];
    }

    private static function getMonthKeys($monthsBack = 4)
    {
        $keys = [];

        for ($i = $monthsBack; $i >= 0; $i--) {
            $keys[] = date('Y-m', strtotime("-{$i} month"));
        }

        return $keys;
    }

    private static function getMonthlySeries($sql, $valueKey = 'total')
    {
        $seriesMap = array_fill_keys(self::getMonthKeys(4), 0);

        $rs = Database::search($sql);

        while ($row = $rs->fetch_assoc()) {
            if (isset($seriesMap[$row['month_key']])) {
                $seriesMap[$row['month_key']] = (float)$row[$valueKey];
            }
        }

        return array_values($seriesMap);
    }

    private static function buildChangeMeta($current, $previous, $period = 'vs last month')
    {
        $current = (float)$current;
        $previous = (float)$previous;

        if ($previous == 0 && $current == 0) {
            return [
                'change' => '0%',
                'change_type' => 'neutral',
                'period' => $period
            ];
        }

        if ($previous == 0 && $current > 0) {
            return [
                'change' => 'New',
                'change_type' => 'positive',
                'period' => $period
            ];
        }

        $percent = (($current - $previous) / $previous) * 100;
        $rounded = round($percent, 1);

        return [
            'change' => ($rounded > 0 ? '+' : '') . $rounded . '%',
            'change_type' => $rounded > 0 ? 'positive' : ($rounded < 0 ? 'negative' : 'neutral'),
            'period' => $period
        ];
    }

    public static function getDashboardSummary()
    {
        $cardData = self::fetchRow("
            SELECT
            (
                SELECT COUNT(*)
                FROM `students`
                INNER JOIN `login_details`
                    ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                WHERE `login_details`.`status_status_id` = 1
            ) AS student_count,

            (
                SELECT COUNT(*)
                FROM `students`
                WHERE MONTH(`date_added`) = MONTH(CURDATE())
                  AND YEAR(`date_added`) = YEAR(CURDATE())
            ) AS monthly_registrations,

            (
                SELECT COUNT(*)
                FROM `students`
                WHERE MONTH(`date_added`) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                  AND YEAR(`date_added`) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
            ) AS previous_month_registrations,

            (
                SELECT COUNT(DISTINCT shi.students_student_id)
                FROM `students_has_intakes` shi
                WHERE MONTH(shi.`addmission_date`) = MONTH(CURDATE())
                  AND YEAR(shi.`addmission_date`) = YEAR(CURDATE())
                  AND EXISTS (
                      SELECT 1
                      FROM `students_has_intakes` old_shi
                      WHERE old_shi.`students_student_id` = shi.`students_student_id`
                        AND old_shi.`addmission_date` < shi.`addmission_date`
                  )
            ) AS monthly_multi_class_registrations,

            (
                SELECT COUNT(*)
                FROM `users`
                WHERE `status_status_id` = 1
                  AND `user_roles_user_role_id` != 8
            ) AS users_count,

            (
                SELECT COUNT(*)
                FROM `users`
                WHERE `status_status_id` = 1
                  AND `user_roles_user_role_id` = 8
            ) AS lecturers_count,

            (
                SELECT COUNT(*)
                FROM `faculties`
            ) AS faculties_count,

            (
                SELECT COUNT(*)
                FROM `programmes`
            ) AS programme_count
                                   
        ");

        $paymentCardData = self::fetchRow("
            WITH payment_receipt_cte AS (
                SELECT
                    cp.course_payments_id,
                  
                    cp.due_date,

                    CASE
                        WHEN cp.due_date < CURDATE() THEN
                            (
                                cp.amount
                                + COALESCE(pp.total_penalty, 0)
                                - COALESCE(
                                    CASE
                                        WHEN cp.discount_type_discount_type_id = 2 THEN cp.discount
                                        WHEN cp.discount_type_discount_type_id = 3 THEN (cp.amount * cp.discount / 100)
                                        ELSE 0
                                    END,
                                0)
                            )
                        ELSE 0
                    END AS gross_amount,

                    COALESCE(SUM(
                        CASE
                            WHEN cpp.payment_status_payment_status_id = 3 THEN
                                COALESCE(cpp.paid_amount, 0)
                                + COALESCE(cpp.panelty_amount, 0)
                                - COALESCE(cpp.discount_amount, 0)
                            ELSE 0
                        END
                    ), 0) AS total_paid,

                    COALESCE(SUM(
                        CASE
                            WHEN cpp.payment_status_payment_status_id = 2 THEN
                                COALESCE(cpp.paid_amount, 0)
                            ELSE 0
                        END
                    ), 0) AS total_pending_approval,

                    COALESCE(SUM(
                        CASE
                            WHEN cpp.payment_status_payment_status_id = 4 THEN
                                COALESCE(cpp.paid_amount, 0)
                            ELSE 0
                        END
                    ), 0) AS total_rejected,

                    COALESCE(SUM(
                        CASE
                            WHEN cpp.payment_status_payment_status_id = 3
                             AND MONTH(cpp.date_paid) = MONTH(CURDATE())
                             AND YEAR(cpp.date_paid) = YEAR(CURDATE())
                            THEN COALESCE(cpp.paid_amount, 0)
                            ELSE 0
                        END
                    ), 0) AS current_paid,

                    COALESCE(SUM(
                        CASE
                            WHEN cpp.payment_status_payment_status_id = 3
                             AND MONTH(cpp.date_paid) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                             AND YEAR(cpp.date_paid) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                            THEN COALESCE(cpp.paid_amount, 0)
                            ELSE 0
                        END
                    ), 0) AS previous_paid,

                    COALESCE(SUM(
                        CASE
                            WHEN cpp.payment_status_payment_status_id = 2
                             AND MONTH(cpp.date_paid) = MONTH(CURDATE())
                             AND YEAR(cpp.date_paid) = YEAR(CURDATE())
                            THEN COALESCE(cpp.paid_amount, 0)
                            ELSE 0
                        END
                    ), 0) AS current_pending_approval,

                    COALESCE(SUM(
                        CASE
                            WHEN cpp.payment_status_payment_status_id = 2
                             AND MONTH(cpp.date_paid) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                             AND YEAR(cpp.date_paid) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                            THEN COALESCE(cpp.paid_amount, 0)
                            ELSE 0
                        END
                    ), 0) AS previous_pending_approval,

                    COALESCE(SUM(
                        CASE
                            WHEN cpp.payment_status_payment_status_id = 4
                             AND MONTH(cpp.date_paid) = MONTH(CURDATE())
                             AND YEAR(cpp.date_paid) = YEAR(CURDATE())
                            THEN COALESCE(cpp.paid_amount, 0)
                            ELSE 0
                        END
                    ), 0) AS current_rejected,

                    COALESCE(SUM(
                        CASE
                            WHEN cpp.payment_status_payment_status_id = 4
                             AND MONTH(cpp.date_paid) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                             AND YEAR(cpp.date_paid) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                            THEN COALESCE(cpp.paid_amount, 0)
                            ELSE 0
                        END
                    ), 0) AS previous_rejected

                FROM course_payments cp
                INNER JOIN payment_plan_has_students_has_intakes ppsi
                    ON cp.payment_plan_has_students_has_intakes_id = ppsi.payment_plan_has_students_has_intakes_id

                LEFT JOIN course_payment_has_payments cpp
                    ON cp.course_payments_id = cpp.course_payments_course_payments_id

                LEFT JOIN (
                    SELECT
                        panelty_payments.course_payments_course_payments_id,
                        SUM(panelty_payments.amount) AS total_penalty
                    FROM panelty_payments
                    GROUP BY panelty_payments.course_payments_course_payments_id
                ) pp
                    ON pp.course_payments_course_payments_id = cp.course_payments_id

                WHERE ppsi.payment_category_payment_category_id = 1

                GROUP BY
                    cp.course_payments_id,
                    
                    cp.due_date,
                    cp.amount,
                    cp.discount,
                    cp.discount_type_discount_type_id,
                    pp.total_penalty
            ),

            payment_summary AS (
                SELECT
                    COALESCE(SUM(gross_amount), 0) AS total_gross_amount,
                    COALESCE(SUM(total_paid), 0) AS total_paid_amount,

                    COALESCE(SUM(current_paid), 0) AS monthly_batch_income,
                    COALESCE(SUM(previous_paid), 0) AS previous_month_income,

                    COALESCE(SUM(total_pending_approval), 0) AS pending_approval_payments,
                    COALESCE(SUM(current_pending_approval), 0) AS current_pending_approval_amount,
                    COALESCE(SUM(previous_pending_approval), 0) AS previous_pending_approval_amount,

                    COALESCE(SUM(total_rejected), 0) AS reject_payments,
                    COALESCE(SUM(current_rejected), 0) AS current_rejected_amount,
                    COALESCE(SUM(previous_rejected), 0) AS previous_rejected_amount,

                    COALESCE(SUM(GREATEST(gross_amount - total_paid, 0)), 0) AS total_awaiting_amount,

                    COALESCE(SUM(
                        CASE
                            WHEN MONTH(due_date) = MONTH(CURDATE())
                             AND YEAR(due_date) = YEAR(CURDATE())
                            THEN GREATEST(gross_amount - total_paid, 0)
                            ELSE 0
                        END
                    ), 0) AS awaiting_amount,

                    COALESCE(SUM(
                        CASE
                            WHEN MONTH(due_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                             AND YEAR(due_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                            THEN GREATEST(gross_amount - total_paid, 0)
                            ELSE 0
                        END
                    ), 0) AS previous_awaiting_amount
                FROM payment_receipt_cte
            )

            SELECT *
            FROM payment_summary
        ");

        $admissionsChart = self::getMonthlySeries("
            SELECT
                DATE_FORMAT(`date_added`, '%Y-%m') AS month_key,
                COUNT(*) AS total
            FROM `students`
            WHERE `date_added` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(`date_added`, '%Y-%m')
        ");

        $incomeChart = self::getMonthlySeries("
            SELECT
                DATE_FORMAT(cpp.date_paid, '%Y-%m') AS month_key,
                SUM(cpp.paid_amount) AS total
            FROM course_payment_has_payments cpp
            INNER JOIN course_payments cp
                ON cpp.course_payments_course_payments_id = cp.course_payments_id
            INNER JOIN payment_plan_has_students_has_intakes ppsi
                ON cp.payment_plan_has_students_has_intakes_id = ppsi.payment_plan_has_students_has_intakes_id
            WHERE cpp.payment_status_payment_status_id IN (2,3,5,6,8)
              AND payment_category_payment_category_id = 1
              AND cpp.date_paid IS NOT NULL
              AND cpp.date_paid >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(cpp.date_paid, '%Y-%m')
        ");

        $pendingApprovalChart = self::getMonthlySeries("
            SELECT
                DATE_FORMAT(cpp.date_paid, '%Y-%m') AS month_key,
                SUM(cpp.paid_amount) AS total
            FROM course_payment_has_payments cpp
            INNER JOIN course_payments cp
                ON cpp.course_payments_course_payments_id = cp.course_payments_id
            INNER JOIN payment_plan_has_students_has_intakes ppsi
                ON cp.payment_plan_has_students_has_intakes_id = ppsi.payment_plan_has_students_has_intakes_id
            WHERE cpp.payment_status_payment_status_id IN (2,5)
              AND payment_category_payment_category_id = 1
              AND cpp.date_paid IS NOT NULL
              AND cpp.date_paid >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(cpp.date_paid, '%Y-%m')
        ");

        $rejectedPaymentsChart = self::getMonthlySeries("
            SELECT
                DATE_FORMAT(cpp.date_paid, '%Y-%m') AS month_key,
                SUM(cpp.paid_amount) AS total
            FROM course_payment_has_payments cpp
            INNER JOIN course_payments cp
                ON cpp.course_payments_course_payments_id = cp.course_payments_id
            INNER JOIN payment_plan_has_students_has_intakes ppsi
                ON cp.payment_plan_has_students_has_intakes_id = ppsi.payment_plan_has_students_has_intakes_id
            WHERE cpp.payment_status_payment_status_id IN (4,7)
              AND payment_category_payment_category_id = 1
              AND cpp.date_paid IS NOT NULL
              AND cpp.date_paid >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(cpp.date_paid, '%Y-%m')
        ");

        $duePaymentsChart = self::getMonthlySeries("
            WITH payment_receipt_cte AS (
                SELECT
                    cp.course_payments_id,
                    cp.due_date,
                    (
                        cp.amount
                        + COALESCE(pp.total_penalty, 0)
                        - COALESCE(
                            CASE
                                WHEN cp.discount_type_discount_type_id = 2 THEN cp.discount
                                WHEN cp.discount_type_discount_type_id = 3 THEN (cp.amount * cp.discount / 100)
                                ELSE 0
                            END
                        , 0)
                    ) AS gross_amount,

                    COALESCE(SUM(
                        CASE
                            WHEN cpp.payment_status_payment_status_id = 3 THEN
                                COALESCE(cpp.paid_amount, 0)
                                + COALESCE(cpp.panelty_amount, 0)
                                - COALESCE(cpp.discount_amount, 0)
                            ELSE 0
                        END
                    ), 0) AS total_paid

                FROM course_payments cp

                LEFT JOIN course_payment_has_payments cpp
                    ON cp.course_payments_id = cpp.course_payments_course_payments_id

                LEFT JOIN (
                    SELECT
                        course_payments_course_payments_id,
                        SUM(amount) AS total_penalty
                    FROM panelty_payments
                    GROUP BY course_payments_course_payments_id
                ) pp
                    ON pp.course_payments_course_payments_id = cp.course_payments_id

                WHERE cp.payment_type_payment_type_id = 1

                GROUP BY
                    cp.course_payments_id,
                    cp.due_date,
                    cp.amount,
                    cp.discount,
                    cp.discount_type_discount_type_id,
                    pp.total_penalty
            )

            SELECT
                DATE_FORMAT(due_date, '%Y-%m') AS month_key,
                SUM(GREATEST(gross_amount - total_paid, 0)) AS total
            FROM payment_receipt_cte
            WHERE due_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(due_date, '%Y-%m')
        ");

        $admissionsMeta = self::buildChangeMeta(
            $cardData['monthly_registrations'],
            $cardData['previous_month_registrations']
        );

        $incomeMeta = self::buildChangeMeta(
            $paymentCardData['monthly_batch_income'],
            $paymentCardData['previous_month_income']
        );

        $dueMeta = self::buildChangeMeta(
            $paymentCardData['awaiting_amount'],
            $paymentCardData['previous_awaiting_amount']
        );

        $pendingMeta = self::buildChangeMeta(
            $paymentCardData['current_pending_approval_amount'],
            $paymentCardData['previous_pending_approval_amount']
        );

        $rejectMeta = self::buildChangeMeta(
            $paymentCardData['current_rejected_amount'],
            $paymentCardData['previous_rejected_amount']
        );

        $dashboardCards = [
            [
                'title' => 'Students',
                'value' => number_format((int)$cardData['student_count']),
                'icon' => 'mdi:account-school-outline',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Total active',
                'chart' => []
            ],
            [
                'title' => 'Monthly New Admissions',
                'value' => number_format((int)$cardData['monthly_registrations']),
                'icon' => 'mdi:school-outline',
                'change' => $admissionsMeta['change'],
                'change_type' => $admissionsMeta['change_type'],
                'period' => $admissionsMeta['period'],
                'chart' => $admissionsChart
            ],
           
            [
                'title' => 'Monthly Income',
                'value' => number_format((float)$paymentCardData['monthly_batch_income'], 2),
                'icon' => 'mdi:cash-multiple',
                'change' => $incomeMeta['change'],
                'change_type' => $incomeMeta['change_type'],
                'period' => $incomeMeta['period'],
                'chart' => $incomeChart
            ],

            [
                'title' => 'Due Payments',
                'value' => number_format((float)$paymentCardData['total_awaiting_amount'], 2),
                'icon' => 'mdi:calendar-clock-outline',
                'change' => $dueMeta['change'],
                'change_type' => $dueMeta['change_type'],
                'period' => $dueMeta['period'],
                'chart' => $duePaymentsChart
            ],
            [
                'title' => 'Pending Approval Payments',
                'value' => number_format((float)$paymentCardData['pending_approval_payments'], 2),
                'icon' => 'mdi:timer-sand',
                'change' => $pendingMeta['change'],
                'change_type' => $pendingMeta['change_type'],
                'period' => $pendingMeta['period'],
                'chart' => $pendingApprovalChart
            ],
            [
                'title' => 'Rejected Payments',
                'value' => number_format((float)$paymentCardData['reject_payments'], 2),
                'icon' => 'mdi:close-circle-outline',
                'change' => $rejectMeta['change'],
                'change_type' => $rejectMeta['change_type'],
                'period' => $rejectMeta['period'],
                'chart' => $rejectedPaymentsChart
            ],
            [
                'title' => 'Users',
                'value' => number_format((int)$cardData['users_count']),
                'icon' => 'mdi:account-group-outline',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Active',
                'chart' => []
            ],
            [
                'title' => 'Faculties',
                'value' => number_format((int)$cardData['faculties_count']),
                'icon' => 'mdi:domain',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Total',
                'chart' => []
            ],
            [
                'title' => 'Programmes',
                'value' => number_format((int)$cardData['programme_count']),
                'icon' => 'mdi:book-open-page-variant-outline',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Total',
                'chart' => []
            ],
            [
                'title' => 'Lecturers',
                'value' => number_format((int)$cardData['lecturers_count']),
                'icon' => 'mdi:teach',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Active',
                'chart' => []
            ],

        ];

        return $dashboardCards;
    }
}
