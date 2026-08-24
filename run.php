<!-- IMPROVED PAYMENT PLANS ACCORDION VIEW -->
<!-- Better looking accordion headers with icons and professional styling -->

<?php
require_once './includes/connection.php';
require_once './includes/UserClass.php';
require_once './includes/Project.php';

$students = Database::search("SELECT `students`.`users_user_id`,`students_has_intakes_id` FROM `students`  INNER JOIN `login_details`
                                                                                ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`

                                                                            INNER JOIN `title`
                                                                                ON `students`.`title_title_id` = `title`.`title_id`
                                                                            
                                                                            INNER JOIN `students_has_intakes`
                                                                                ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
                                                                                WHERE `counselor_user_id` IS NULL AND `students`.`lead_student_status_lead_student_status_id` IS NOT NULL
");

while ($student = $students->fetch_assoc()) {
    Database::iud("UPDATE `students_has_intakes` SET `counselor_user_id`='" . $student['users_user_id'] . "' 
    WHERE `students_has_intakes_id`='" . $student['students_has_intakes_id'] . "'");
}
