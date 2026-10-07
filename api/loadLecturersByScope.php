<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

/* ==========================================================================
 * Returns lecturers eligible for a BROAD-scope lecture (branch / faculty /
 * programme / intake / level — i.e. no specific subject chosen), or every
 * active lecturer institution-wide when type=all (i.e. even branch was
 * wildcarded, so there is no concrete scope left to filter by). Unlike
 * loadLecturers.php (which returns one <option> per `lecturers_id` row,
 * since that's subject-bound), this returns one <option> per PERSON
 * (`users_user_id`), because the same lecturer can have several
 * `lecturers_id` rows (one per subject) inside a broad scope and should
 * only appear once.
 *
 * value="{users_user_id}" — this is what gets POSTed back as
 * `broad_lecturer_user_id` in scheduleLecture.php.
 *
 * // ASSUMED — verify against your actual schema:
 *   subjects.levels_level_id        (confirmed elsewhere in your codebase)
 *   levels.intakes_intake_id        (assumed, same naming convention)
 *   intakes.programmes_programme_id (assumed)
 *   programmes.faculties_faculty_id (assumed)
 *   faculties.branches_branch_id    (assumed)
 * If any of these column names are wrong, MySQL will throw an "unknown
 * column" error rather than silently returning wrong data — check your
 * error log against the JOIN chain below if this 400s.
 * ========================================================================== */

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {

        $type = isset($_GET['type']) ? trim($_GET['type']) : '';
        $id = isset($_GET['id']) ? trim($_GET['id']) : '';

        // 'all' = entire institution — no ancestor in the cascade was
        // concretely selected (every level up to Branch was wildcarded, or
        // Branch itself is "— Entire Institution —"). No id is required
        // for this type, since there's nothing left to filter by.
        $allowedTypes = ['branch', 'faculty', 'programme', 'intake', 'level', 'all'];

        if (!in_array($type, $allowedTypes, true)) {
            $status->type = "Invalid scope type";
        }
        if ($type !== 'all' && ($id == 0 || $id === '')) {
            $status->id = "Please select a value for this scope";
        }

        if (!property_exists($status, 'type') && !property_exists($status, 'id')) {

            // Filter column keyed by scope type — the join chain below is
            // always built in full; only the WHERE clause changes.
            $filterColumnByType = [
                'branch'    => 'faculties.branches_branch_id',
                'faculty'   => 'programmes.faculties_faculty_id',
                'programme' => 'intakes.programmes_programme_id',
                'intake'    => 'levels.intakes_intake_id',
                'level'     => 'subjects.levels_level_id',
            ];

            $whereClause = "WHERE `lecturers`.`status_status_id`='1' AND `users`.`status_status_id`='1'";
            if ($type !== 'all') {
                $filterColumn = $filterColumnByType[$type];
                $whereClause .= " AND " . $filterColumn . "='" . Database::escape($id) . "'";
            }

?>
            <option value="0" selected>- Select -</option>
            <?php
            $query = "SELECT DISTINCT `users`.`user_id` AS `users_user_id`, `user_fname`, `user_lname`, `title` FROM `lecturers`
                                    INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                    INNER JOIN `subjects` ON `lecturers`.`subjects_subject_id` = `subjects`.`subject_id`
                                    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                    INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                    INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                    INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                                    " . $whereClause . "
                                    ORDER BY `user_fname`, `user_lname`";

            $lecturers = Database::search($query);
            while ($data = $lecturers->fetch_assoc()) {
            ?>
                <option value="<?php echo $data["users_user_id"]; ?>"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></option>
<?php
            }
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error') || property_exists($status, 'type') || property_exists($status, 'id')) {
    echo (json_encode($status));
}