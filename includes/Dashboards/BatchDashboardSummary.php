<?php

class BatchDashboardSummary
{

    private static function fetchRow($sql)
    {
        return Database::search($sql)->fetch_assoc();
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
        $current  = (float)$current;
        $previous = (float)$previous;

        if ($previous == 0 && $current == 0) {
            return ['change' => '0%', 'change_type' => 'neutral', 'period' => $period];
        }

        if ($previous == 0 && $current > 0) {
            return ['change' => 'New', 'change_type' => 'positive', 'period' => $period];
        }

        $percent = (($current - $previous) / $previous) * 100;
        $rounded = round($percent, 1);

        return [
            'change'      => ($rounded > 0 ? '+' : '') . $rounded . '%',
            'change_type' => $rounded > 0 ? 'positive' : ($rounded < 0 ? 'negative' : 'neutral'),
            'period'      => $period,
        ];
    }

    /**
     * Main entry point.
     *
     * @param int $intakeId  students_has_intakes.intakes_intake_id
     * @return array         Same structure as AdminDashboardSummary::getDashboardSummary()
     */
    public static function getBatchDashboardSummary(int $intakeId): array
    {
        // ── 1. STUDENT COUNTS ────────────────────────────────────────────────
        $cardData = self::fetchRow("
            SELECT

            /* Total students enrolled in this batch */
            (
                SELECT COUNT(*)
                FROM `students_has_intakes`
                INNER JOIN `students` AS `s`
                    ON `s`.`student_id` = `students_has_intakes`.`students_student_id`
                WHERE `s`.`enrolled_status` = 'ENROLLED'
                AND (`shi`.`lead_student_status_lead_student_status_id` IS NULL OR `shi`.`lead_student_status_lead_student_status_id` = '17')

                WHERE `intakes_intake_id` = '" . $intakeId . "'
            ) AS total_students,

            /* Active students (status = 1) */
            (
                SELECT COUNT(*)
                FROM `students_has_intakes`
                WHERE `intakes_intake_id` = '" . $intakeId . "'
                  AND `status_status_id` = 1
            ) AS active_students,

            /* Inactive students (status != 1) */
            (
                SELECT COUNT(*)
                FROM `students_has_intakes`
                WHERE `intakes_intake_id` = '" . $intakeId . "'
                  AND `status_status_id` != 1
            ) AS inactive_students,

            /* Payment Completed Students —
               all course_payments for their intake have at least one approved payment
               covering the full amount */
            (
                SELECT COUNT(DISTINCT shi.`students_has_intakes_id`)
                FROM `students_has_intakes` shi
                WHERE shi.`intakes_intake_id` = '" . $intakeId . "'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM `course_payments` cp
                      INNER JOIN `payment_plan_has_students_has_intakes` pphsi
                          ON cp.`payment_plan_has_students_has_intakes_id` = pphsi.`payment_plan_has_students_has_intakes_id`
                      WHERE pphsi.`students_has_intakes_students_has_intakes_id` = shi.`students_has_intakes_id`
                        AND (
                            SELECT COALESCE(SUM(
                                CASE WHEN cpp.`payment_status_payment_status_id` = 3
                                     THEN COALESCE(cpp.`paid_amount`, 0)
                                          + COALESCE(cpp.`panelty_amount`, 0)
                                          - COALESCE(cpp.`discount_amount`, 0)
                                     ELSE 0 END
                            ), 0)
                            FROM `course_payment_has_payments` cpp
                            WHERE cpp.`course_payments_course_payments_id` = cp.`course_payments_id`
                        ) < cp.`amount`
                  )
            ) AS payment_completed_students,

            /* ── ASSIGNMENTS ─────────────────────────────────────────────── */

            /* Total assignments linked to subjects in this intake */
            (
                SELECT COUNT(DISTINCT a.`assignment_id`)
                FROM `assignments` a
                INNER JOIN `subjects` s
                    ON a.`subjects_subject_id` = s.`subject_id`
                INNER JOIN `levels` l
                    ON s.`levels_level_id` = l.`level_id`
                WHERE l.`intakes_intake_id` = '" . $intakeId . "'
            ) AS total_assignments,

            /* Lapsed assignments — deadline has passed and student has NOT submitted */
            (
                SELECT COUNT(*)
                FROM `students_has_assignments` sha
                INNER JOIN `assignments` a
                    ON sha.`assignments_assignment_id` = a.`assignment_id`
                INNER JOIN `subjects` s
                    ON a.`subjects_subject_id` = s.`subject_id`
                INNER JOIN `levels` l
                    ON s.`levels_level_id` = l.`level_id`
                INNER JOIN `students_has_intakes` shi
                    ON sha.`students_student_id` = shi.`students_student_id`
                WHERE l.`intakes_intake_id` = '" . $intakeId . "'
                  AND shi.`intakes_intake_id` = '" . $intakeId . "'
                  AND a.`end_date` < CURDATE()
                  AND sha.`uploaded_at` IS NULL
            ) AS lapsed_assignments,

            /* Upcoming assignments — deadline is today or in the future */
            (
                SELECT COUNT(DISTINCT a.`assignment_id`)
                FROM `assignments` a
                INNER JOIN `subjects` s
                    ON a.`subjects_subject_id` = s.`subject_id`
                INNER JOIN `levels` l
                    ON s.`levels_level_id` = l.`level_id`
                WHERE l.`intakes_intake_id` = '" . $intakeId . "'
                  AND a.`start_date` >= CURDATE()
            ) AS upcoming_assignments,

            /* Assignments completed — student submitted AND lecturer marked */
            (
                SELECT COUNT(DISTINCT sha.`students_has_assignments_id`)
                FROM `students_has_assignments` sha
                INNER JOIN `assignments` a
                    ON sha.`assignments_assignment_id` = a.`assignment_id`
                INNER JOIN `subjects` s
                    ON a.`subjects_subject_id` = s.`subject_id`
                INNER JOIN `levels` l
                    ON s.`levels_level_id` = l.`level_id`
                INNER JOIN `students_has_intakes` shi
                    ON sha.`students_student_id` = shi.`students_student_id`
                WHERE l.`intakes_intake_id` = '" . $intakeId . "'
                  AND shi.`intakes_intake_id` = '" . $intakeId . "'
                  AND sha.`uploaded_at` IS NOT NULL
                  AND sha.`assignment_marks_status_assignment_marks_status_id` = 4  
            ) AS assignments_completed_students

        ");

        // ── 2. PAYMENT SUMMARY ───────────────────────────────────────────────
        $paymentData = self::fetchRow("
            WITH payment_cte AS (
                SELECT
                    cp.`course_payments_id`,
                    phshi.`students_has_intakes_students_has_intakes_id`,
                    shi.`status_status_id` AS student_status,
                   (
                        cp.`amount`
                        + COALESCE(pp.`total_penalty`, 0)

                        - COALESCE(
                            CASE
                                WHEN cp.`discount_type_discount_type_id` = 2 
                                    THEN cp.`discount`
                                WHEN cp.`discount_type_discount_type_id` = 3 
                                    THEN (cp.`amount` * cp.`discount` / 100)
                                ELSE 0
                            END
                        , 0)

                        + COALESCE(SUM(
                            CASE 
                                WHEN cpp.`payment_status_payment_status_id` = 8
                                    THEN COALESCE(cpp.`panelty_amount`, 0)
                                ELSE 0 
                            END
                        ), 0)

                        - COALESCE(SUM(
                            CASE 
                                WHEN cpp.`payment_status_payment_status_id` = 8
                                    THEN COALESCE(cpp.`discount_amount`, 0)
                                ELSE 0 
                            END
                        ), 0)
                    ) AS gross_amount,

                    COALESCE(SUM(
                        CASE WHEN cpp.`payment_status_payment_status_id` = 8
                             THEN COALESCE(cpp.`paid_amount`, 0)
                             ELSE 0 END
                    ), 0) AS total_paid,

                    /* This month approved */
                    COALESCE(SUM(
                        CASE WHEN cpp.`payment_status_payment_status_id` = 8
                                  AND MONTH(cpp.`date_paid`) = MONTH(CURDATE())
                                  AND YEAR(cpp.`date_paid`)  = YEAR(CURDATE())
                             THEN COALESCE(cpp.`paid_amount`, 0)
                             ELSE 0 END
                    ), 0) AS this_month_paid,

                    /* Previous month approved */
                    COALESCE(SUM(
                        CASE WHEN cpp.`payment_status_payment_status_id` = 8
                                  AND MONTH(cpp.`date_paid`) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                                  AND YEAR(cpp.`date_paid`)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                             THEN COALESCE(cpp.`paid_amount`, 0)
                             ELSE 0 END
                    ), 0) AS prev_month_paid,

                    /* Pending approval */
                    COALESCE(SUM(
                        CASE WHEN cpp.`payment_status_payment_status_id` IN (2,5)
                             THEN COALESCE(cpp.`paid_amount`, 0)
                             ELSE 0 END
                    ), 0) AS total_pending_approval,

                    /* This month pending approval */
                    COALESCE(SUM(
                        CASE WHEN cpp.`payment_status_payment_status_id` IN (2,5)
                                  AND MONTH(cpp.`date_paid`) = MONTH(CURDATE())
                                  AND YEAR(cpp.`date_paid`)  = YEAR(CURDATE())
                             THEN COALESCE(cpp.`paid_amount`, 0)
                             ELSE 0 END
                    ), 0) AS this_month_pending_approval,

                    /* Previous month pending approval */
                    COALESCE(SUM(
                        CASE WHEN cpp.`payment_status_payment_status_id` IN (2,5)
                                  AND MONTH(cpp.`date_paid`) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                                  AND YEAR(cpp.`date_paid`)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                             THEN COALESCE(cpp.`paid_amount`, 0)
                             ELSE 0 END
                    ), 0) AS prev_month_pending_approval,

                    /* Rejected */
                    COALESCE(SUM(
                        CASE WHEN cpp.`payment_status_payment_status_id` IN (4,7)
                             THEN COALESCE(cpp.`paid_amount`, 0)
                             ELSE 0 END
                    ), 0) AS total_rejected,

                    /* This month rejected */
                    COALESCE(SUM(
                        CASE WHEN cpp.`payment_status_payment_status_id` IN (4,7)
                                  AND MONTH(cpp.`date_paid`) = MONTH(CURDATE())
                                  AND YEAR(cpp.`date_paid`)  = YEAR(CURDATE())
                             THEN COALESCE(cpp.`paid_amount`, 0)
                             ELSE 0 END
                    ), 0) AS this_month_rejected,

                    /* Previous month rejected */
                    COALESCE(SUM(
                        CASE WHEN cpp.`payment_status_payment_status_id` IN (4,7)
                                  AND MONTH(cpp.`date_paid`) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                                  AND YEAR(cpp.`date_paid`)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                             THEN COALESCE(cpp.`paid_amount`, 0)
                             ELSE 0 END
                    ), 0) AS prev_month_rejected
                FROM `course_payments` cp
                INNER JOIN `payment_plan_has_students_has_intakes` phshi
                    ON cp.`payment_plan_has_students_has_intakes_id` = phshi.`payment_plan_has_students_has_intakes_id`
                INNER JOIN `students_has_intakes` shi
                    ON phshi.`students_has_intakes_students_has_intakes_id` = shi.`students_has_intakes_id`
                LEFT JOIN `course_payment_has_payments` cpp
                    ON cp.`course_payments_id` = cpp.`course_payments_course_payments_id`
                LEFT JOIN (
                    SELECT `course_payments_course_payments_id`, SUM(`amount`) AS `total_penalty`
                    FROM `panelty_payments`
                    GROUP BY `course_payments_course_payments_id`
                ) pp ON pp.`course_payments_course_payments_id` = cp.`course_payments_id`
                WHERE shi.`intakes_intake_id` = '" . $intakeId . "'
                GROUP BY
                    cp.`course_payments_id`,
                   
                    shi.`status_status_id`,
                    cp.`amount`,
                    cp.`discount`,
                    cp.`discount_type_discount_type_id`,
                    pp.`total_penalty`
            )
            SELECT
                /* Total batch income — all approved payments ever */
                COALESCE(SUM(gross_amount), 0)                              AS total_batch_income,

                /* Total received income — sum of all approved paid amounts */
                COALESCE(SUM(total_paid), 0)                                AS total_received_income,

                /* Total balance due — unpaid portion across all payments */
                COALESCE(SUM(GREATEST(gross_amount - total_paid, 0)), 0)    AS total_balance_due,

                /* Balance due from inactive students only */
                COALESCE(SUM(
                    CASE WHEN student_status != 1
                         THEN GREATEST(gross_amount - total_paid, 0)
                         ELSE 0 END
                ), 0)                                                        AS balance_due_inactive_students,

                /* This month income */
                COALESCE(SUM(this_month_paid), 0)                           AS this_month_income,

                /* Previous month income (for change meta) */
                COALESCE(SUM(prev_month_paid), 0)                           AS prev_month_income,

                /* This month due payments (balance due on payments due this month) */
                COALESCE(SUM(this_month_pending_approval), 0)               AS this_month_due_payments,

                /* Previous month due payments */
                COALESCE(SUM(prev_month_pending_approval), 0)               AS prev_month_due_payments,

                /* Pending approval total */
                COALESCE(SUM(total_pending_approval), 0)                    AS pending_approval_amount,

                /* This month pending approval */
                COALESCE(SUM(this_month_pending_approval), 0)               AS this_month_pending_approval,

                /* Previous month pending approval */
                COALESCE(SUM(prev_month_pending_approval), 0)               AS prev_month_pending_approval,

                /* Rejected total */
                COALESCE(SUM(total_rejected), 0)                            AS rejected_amount,

                /* This month rejected */
                COALESCE(SUM(this_month_rejected), 0)                       AS this_month_rejected,

                /* Previous month rejected */
                COALESCE(SUM(prev_month_rejected), 0)                       AS prev_month_rejected

            FROM payment_cte
        ");

        // ── 3. MONTHLY CHART SERIES ──────────────────────────────────────────

        $incomeChart = self::getMonthlySeries("
            SELECT DATE_FORMAT(cpp.`date_paid`, '%Y-%m') AS month_key,
                   SUM(cpp.`paid_amount`) AS total
            FROM `course_payment_has_payments` cpp
            INNER JOIN `course_payments` cp
                ON cpp.`course_payments_course_payments_id` = cp.`course_payments_id`
            INNER JOIN `payment_plan_has_students_has_intakes` phshi
                ON cp.`payment_plan_has_students_has_intakes_id` = phshi.`payment_plan_has_students_has_intakes_id`
            INNER JOIN `students_has_intakes` shi
                ON phshi.`students_has_intakes_students_has_intakes_id` = shi.`students_has_intakes_id`
            WHERE shi.`intakes_intake_id` = '" . $intakeId . "'
              AND cpp.`payment_status_payment_status_id` = 8
              AND cpp.`date_paid` IS NOT NULL
              AND cpp.`date_paid` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(cpp.`date_paid`, '%Y-%m')
        ");

        $pendingApprovalChart = self::getMonthlySeries("
            SELECT DATE_FORMAT(cpp.`date_paid`, '%Y-%m') AS month_key,
                   SUM(cpp.`paid_amount`) AS total
            FROM `course_payment_has_payments` cpp
            INNER JOIN `course_payments` cp
                ON cpp.`course_payments_course_payments_id` = cp.`course_payments_id`
            INNER JOIN `payment_plan_has_students_has_intakes` phshi
                ON cp.`payment_plan_has_students_has_intakes_id` = phshi.`payment_plan_has_students_has_intakes_id`
            INNER JOIN `students_has_intakes` shi
                ON phshi.`students_has_intakes_students_has_intakes_id` = shi.`students_has_intakes_id`
            WHERE shi.`intakes_intake_id` = '" . $intakeId . "'
              AND cpp.`payment_status_payment_status_id` IN (2,5)
              AND cpp.`date_paid` IS NOT NULL
              AND cpp.`date_paid` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(cpp.`date_paid`, '%Y-%m')
        ");

        $rejectedChart = self::getMonthlySeries("
            SELECT DATE_FORMAT(cpp.`date_paid`, '%Y-%m') AS month_key,
                   SUM(cpp.`paid_amount`) AS total
            FROM `course_payment_has_payments` cpp
            INNER JOIN `course_payments` cp
                ON cpp.`course_payments_course_payments_id` = cp.`course_payments_id`
            INNER JOIN `payment_plan_has_students_has_intakes` phshi
                ON cp.`payment_plan_has_students_has_intakes_id` = phshi.`payment_plan_has_students_has_intakes_id`
            INNER JOIN `students_has_intakes` shi
                ON phshi.`students_has_intakes_students_has_intakes_id` = shi.`students_has_intakes_id`
            WHERE shi.`intakes_intake_id` = '" . $intakeId . "'
              AND cpp.`payment_status_payment_status_id` IN (4,7)
              AND cpp.`date_paid` IS NOT NULL
              AND cpp.`date_paid` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(cpp.`date_paid`, '%Y-%m')
        ");

        $assignmentsChart = self::getMonthlySeries("
            SELECT DATE_FORMAT(sha.`uploaded_at`, '%Y-%m') AS month_key,
                   COUNT(*) AS total
            FROM `students_has_assignments` sha
            INNER JOIN `assignments` a
                ON sha.`assignments_assignment_id` = a.`assignment_id`
            INNER JOIN `subjects` s
                ON a.`subjects_subject_id` = s.`subject_id`
            INNER JOIN `levels` l
                ON s.`levels_level_id` = l.`level_id`
            INNER JOIN `students_has_intakes` shi
                ON sha.`students_student_id` = shi.`students_student_id`
            WHERE l.`intakes_intake_id` = '" . $intakeId . "'
              AND shi.`intakes_intake_id` = '" . $intakeId . "'
              AND sha.`uploaded_at` IS NOT NULL
              AND sha.`uploaded_at` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(sha.`uploaded_at`, '%Y-%m')
        ");

        // ── 4. CHANGE META ───────────────────────────────────────────────────
        $incomeMeta           = self::buildChangeMeta($paymentData['this_month_income'],          $paymentData['prev_month_income']);
        $dueMeta              = self::buildChangeMeta($paymentData['this_month_due_payments'],     $paymentData['prev_month_due_payments']);
        $pendingMeta          = self::buildChangeMeta($paymentData['this_month_pending_approval'], $paymentData['prev_month_pending_approval']);
        $rejectedMeta         = self::buildChangeMeta($paymentData['this_month_rejected'],        $paymentData['prev_month_rejected']);

        // ── 5. BUILD CARDS ARRAY (same structure as AdminDashboardSummary) ───
        $batchCards = [

            // ── ROW 1: Student counts ────────────────────────────────────────
            [
                'title'       => 'Total Students',
                'value'       => number_format((int)$cardData['total_students']),
                'icon'        => 'mdi:account-group-outline',
                'change'      => '',
                'change_type' => 'neutral',
                'period'      => 'Enrolled in batch',
                'chart'       => [],
            ],
            [
                'title'       => 'Active Students',
                'value'       => number_format((int)$cardData['active_students']),
                'icon'        => 'mdi:account-check-outline',
                'change'      => '',
                'change_type' => 'neutral',
                'period'      => 'Currently active',
                'chart'       => [],
            ],
            [
                'title'       => 'Inactive Students',
                'value'       => number_format((int)$cardData['inactive_students']),
                'icon'        => 'mdi:account-off-outline',
                'change'      => '',
                'change_type' => 'neutral',
                'period'      => 'Not active',
                'chart'       => [],
            ],
            [
                'title'       => 'Payment Completed Students',
                'value'       => number_format((int)$cardData['payment_completed_students']),
                'icon'        => 'mdi:account-cash-outline',
                'change'      => '',
                'change_type' => 'neutral',
                'period'      => 'Fully paid',
                'chart'       => [],
            ],

            // ── ROW 2: Assignments ───────────────────────────────────────────
            [
                'title'       => 'Assignments',
                'value'       => number_format((int)$cardData['total_assignments']),
                'icon'        => 'mdi:file-document-outline',
                'change'      => '',
                'change_type' => 'neutral',
                'period'      => 'Total in batch',
                'chart'       => $assignmentsChart,
            ],
            [
                'title'       => 'Lapsed Assignments',
                'value'       => number_format((int)$cardData['lapsed_assignments']),
                'icon'        => 'mdi:file-clock-outline',
                'change'      => '',
                'change_type' => 'neutral',
                'period'      => 'Deadline passed',
                'chart'       => [],
            ],
            [
                'title'       => 'Up-Coming Assignments',
                'value'       => number_format((int)$cardData['upcoming_assignments']),
                'icon'        => 'mdi:file-upload-outline',
                'change'      => '',
                'change_type' => 'neutral',
                'period'      => 'Deadline upcoming',
                'chart'       => [],
            ],
            [
                'title'       => 'Assignments Completed Students',
                'value'       => number_format((int)$cardData['assignments_completed_students']),
                'icon'        => 'mdi:file-document-check-outline',
                'change'      => '',
                'change_type' => 'neutral',
                'period'      => 'Submitted & marked',
                'chart'       => [],
            ],

            // ── ROW 3: Payment totals ────────────────────────────────────────
            [
                'title'       => 'Total Batch Income',
                'value'       => number_format((float)$paymentData['total_batch_income'], 2),
                'icon'        => 'mdi:cash-multiple',
                'change'      => '',
                'change_type' => 'neutral',
                'period'      => 'Total income from this batch',
                'chart'       => $incomeChart,
            ],
            [
                'title'       => 'Total Received Income',
                'value'       => number_format((float)$paymentData['total_received_income'], 2),
                'icon'        => 'mdi:cash-check',
                'change'      => $incomeMeta['change'],
                'change_type' => $incomeMeta['change_type'],
                'period'      => $incomeMeta['period'],
                'chart'       => $incomeChart,
            ],
            [
                'title'       => 'Total Balance Due',
                'value'       => number_format((float)$paymentData['total_balance_due'], 2),
                'icon'        => 'mdi:calendar-clock-outline',
                'change'      => $dueMeta['change'],
                'change_type' => $dueMeta['change_type'],
                'period'      => $dueMeta['period'],
                'chart'       => [],
            ],
            [
                'title'       => 'Total Balance Dues from Inactive Students',
                'value'       => number_format((float)$paymentData['balance_due_inactive_students'], 2),
                'icon'        => 'mdi:account-alert-outline',
                'change'      => '',
                'change_type' => 'neutral',
                'period'      => 'From inactive only',
                'chart'       => [],
            ],

            // ── ROW 4: This month payments ───────────────────────────────────
            [
                'title'       => 'This Month Income',
                'value'       => number_format((float)$paymentData['this_month_income'], 2),
                'icon'        => 'mdi:cash-fast',
                'change'      => $incomeMeta['change'],
                'change_type' => $incomeMeta['change_type'],
                'period'      => $incomeMeta['period'],
                'chart'       => $incomeChart,
            ],
            [
                'title'       => 'This Month Due Payments',
                'value'       => number_format((float)$paymentData['this_month_due_payments'], 2),
                'icon'        => 'mdi:timer-sand',
                'change'      => $dueMeta['change'],
                'change_type' => $dueMeta['change_type'],
                'period'      => $dueMeta['period'],
                'chart'       => [],
            ],
            [
                'title'       => 'Pending Approval',
                'value'       => number_format((float)$paymentData['pending_approval_amount'], 2),
                'icon'        => 'mdi:clock-outline',
                'change'      => $pendingMeta['change'],
                'change_type' => $pendingMeta['change_type'],
                'period'      => $pendingMeta['period'],
                'chart'       => $pendingApprovalChart,
            ],
            [
                'title'       => 'Rejected Payments',
                'value'       => number_format((float)$paymentData['rejected_amount'], 2),
                'icon'        => 'mdi:close-circle-outline',
                'change'      => $rejectedMeta['change'],
                'change_type' => $rejectedMeta['change_type'],
                'period'      => $rejectedMeta['period'],
                'chart'       => $rejectedChart,
            ],
        ];

        return $batchCards;
    }
}
