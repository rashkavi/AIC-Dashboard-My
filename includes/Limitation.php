<?php
class Limitation
{
    private const STUDENT_LIMIT = 100;

    public static function getActiveStudentCount()
    {
        $result = Database::search("
            SELECT COUNT(*) AS total
            FROM students_has_intakes
            WHERE status_status_id = 1
        ");

        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['total'];
        }

        return 0;
    }

    public static function availableStudents()
    {
        $count = self::getActiveStudentCount();
        return $count < self::STUDENT_LIMIT;
    }

    public static function getLimitExceededMessage()
    {
        $activeIntakeStudentsCount = self::getActiveStudentCount();
        $limit = self::STUDENT_LIMIT;

        return "
        <div class='col-12 p-4 component-theme'>
            <div class='row justify-content-center'>
                <div class='col-12 col-lg-8 container my-4'>
                    <div class='card border-danger shadow-sm'>

                        <div class='card-header bg-danger text-white'>
                            <h5 class='mb-0'>Student Limit Reached</h5>
                        </div>

                        <div class='card-body'>
                            <p class='fw-semibold text-danger mb-4'>
                                You have reached the maximum allowed number of active students.
                            </p>

                            <div class='p-3 rounded-3 border border-danger bg-light-subtle mb-3'>
                                <h6 class='text-danger text-uppercase fw-bold mb-3 text-center'>
                                    Subscription Summary
                                </h6>

                                <ul class='list-group small'>
                                    <li class='list-group-item d-flex justify-content-between'>
                                        <span>Total Active Students:</span>
                                        <strong>{$activeIntakeStudentsCount} / {$limit}</strong>
                                    </li>

                                    <li class='list-group-item d-flex justify-content-between'>
                                        <span>Subscription Type:</span>
                                        <strong>Fixed Monthly Plan</strong>
                                    </li>

                                    <li class='list-group-item d-flex justify-content-between'>
                                        <span>Applies To:</span>
                                        <strong>All Intakes</strong>
                                    </li>
                                </ul>
                            </div>

                            <div class='text-center'>
                                <span class='fw-semibold text-danger'>
                                    Please deactivate existing students or upgrade your subscription.
                                </span>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        ";
    }
}
