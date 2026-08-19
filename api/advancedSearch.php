<?php

/**
 * ==========================================================================
 *  advanced-search-api.php
 * ==========================================================================
 *  Universal / global search across the academic hierarchy:
 *
 *      Branches -> Faculties -> Programmes -> Intakes (Batches) -> Students
 *
 *  A user can type ANY keyword combination in ANY order — a branch name,
 *  a programme name, a batch name, a student's name/NIC/passport/mobile,
 *  or a mix of these — and this endpoint returns matching rows from every
 *  relevant table, each one carrying its full breadcrumb (branch > faculty
 *  > programme > batch) plus useful counts, so the front-end can render a
 *  single, clear "line list" result view without extra round trips.
 *
 *  Example:  q = "Batch 5"
 *      -> matches ANY intake named e.g. "Batch 5" under ANY branch/programme,
 *         each result shows its own branch + programme + student count.
 *
 *  Example:  q = "AIC Batch 5"
 *      -> "AIC" matches the branch name, "Batch" + "5" match the intake
 *         name — both keywords must be satisfied, but each can be satisfied
 *         by a different column in the hierarchy.
 *
 * --------------------------------------------------------------------------
 *  GET params
 * --------------------------------------------------------------------------
 *   q          string   required. Free text, e.g. "Batch 5", "AIC Campus"
 *   type       string   all | branch | faculty | programme | intake | student
 *                       (default: all)
 *   mode       string   instant | full   (default: instant)
 *                        instant -> small, fast, used for the live dropdown
 *                        full    -> paginated, used for the "view all" list
 *   page       int      only used when mode=full (default: 1)
 *   branch_id  int      optional, narrows results to one branch
 *   programme_id int    optional, narrows results to one programme
 *   intake_id  int      optional, used to pull the full student roster of
 *                        one specific batch (pair with type=student)
 *
 * --------------------------------------------------------------------------
 *  Response (JSON)
 * --------------------------------------------------------------------------
 *   {
 *     "success": true,
 *     "query": "batch 5",
 *     "mode": "instant",
 *     "counts":  { "branches":1, "faculties":2, "programmes":3, "intakes":6, "students":128 },
 *     "results": { "branches":[...], "faculties":[...], "programmes":[...], "intakes":[...], "students":[...] },
 *     "page": 1,
 *     "has_more": { "branches": false, "faculties": false, ... }
 *   }
 * ==========================================================================
 */

// --------------------------------------------------------------------------
// 0. Bootstrap — your Database class is static (mysqli under the hood), so
//    there's no `new Database()` / `$db` instance anywhere in this file —
//    everything is called as Database::search() / Database::escape().
// --------------------------------------------------------------------------
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

header('Content-Type: application/json; charset=utf-8');

// Optional: keep this endpoint behind your existing auth/session guard,
// the same way your other admin/portal APIs are protected.
// if (!isset($_SESSION['user_id'])) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit;
// }

// --------------------------------------------------------------------------
// 1. Read + sanitize input
// --------------------------------------------------------------------------
$rawQuery    = isset($_GET['q']) ? trim($_GET['q']) : '';
$type        = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'all';
$mode        = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'instant';
$page        = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$branchId    = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int) $_GET['branch_id'] : null;
$programmeId = isset($_GET['programme_id']) && $_GET['programme_id'] !== '' ? (int) $_GET['programme_id'] : null;
$intakeId    = isset($_GET['intake_id']) && $_GET['intake_id'] !== '' ? (int) $_GET['intake_id'] : null;

$allowedTypes = ['all', 'branch', 'faculty', 'programme', 'intake', 'student'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}
if (!in_array($mode, ['instant', 'full'], true)) {
    $mode = 'instant';
}

// A drill-down into one batch's full roster is allowed even with no
// keyword typed (e.g. clicking "View all 42 students in Batch 5").
if ($rawQuery === '' && !($intakeId && $type === 'student')) {
    echo json_encode([
        'success' => false,
        'message' => 'Type at least one keyword to search.',
    ]);
    exit;
}

// Split "Batch 5" -> ["Batch", "5"]. Every keyword must match SOMEWHERE
// in the searchable columns (AND across keywords, OR across columns).
$keywords = array_values(array_filter(
    preg_split('/\s+/', $rawQuery),
    static function ($w) {
        return mb_strlen($w) > 0;
    }
));

// Limits — small for the live typeahead, larger + paginated for "view all".
$instantLimit = 6;
$fullLimit    = 20;
$limit        = $mode === 'full' ? $fullLimit : $instantLimit;
$offset       = $mode === 'full' ? ($page - 1) * $fullLimit : 0;

// --------------------------------------------------------------------------
// 2. Helpers
// --------------------------------------------------------------------------

/**
 * Database::search() returns a raw mysqli_result (or false on a failed
 * query) — never an array. This normalizes it to a plain assoc array,
 * the same shape the rest of this file expects.
 */
function dbRows($mysqliResult): array
{
    if (!$mysqliResult instanceof mysqli_result) {
        return [];
    }
    return $mysqliResult->fetch_all(MYSQLI_ASSOC);
}

/**
 * Every level of the hierarchy is searchable both UP (its own parents'
 * names) and DOWN (anything nested inside it) so that a keyword typed
 * anywhere in the chain — branch, faculty, programme, batch, or a
 * student's own details — surfaces every level connected to it.
 * e.g. typing a programme name and viewing the Faculties/Branches tab
 * should still show the faculty/branch that programme belongs to.
 */

function buildBranchKeywordWhere(array $keywords): string
{
    if (empty($keywords)) {
        return '1=1';
    }
    $groups = [];
    foreach ($keywords as $kw) {
        $kwEsc = Database::escape('%' . $kw . '%');
        $groups[] = "(
            b.branch LIKE '$kwEsc'
            OR EXISTS (
                SELECT 1 FROM faculties fx
                LEFT JOIN programmes px ON px.faculties_faculty_id = fx.faculty_id
                LEFT JOIN intakes ix ON ix.programmes_programme_id = px.programme_id
                LEFT JOIN students_has_intakes shx ON shx.intakes_intake_id = ix.intake_id
                LEFT JOIN students stx ON stx.student_id = shx.students_student_id
                WHERE fx.branches_branch_id = b.branch_id
                  AND (
                        fx.faculty LIKE '$kwEsc'
                        OR px.programme LIKE '$kwEsc'
                        OR ix.intake LIKE '$kwEsc'
                        OR stx.full_name LIKE '$kwEsc'
                        OR stx.name_with_initials LIKE '$kwEsc'
                        OR stx.nic LIKE '$kwEsc'
                        OR stx.passport LIKE '$kwEsc'
                        OR stx.mobile LIKE '$kwEsc'
                  )
            )
        )";
    }
    return implode(' AND ', $groups);
}

function buildFacultyKeywordWhere(array $keywords): string
{
    if (empty($keywords)) {
        return '1=1';
    }
    $groups = [];
    foreach ($keywords as $kw) {
        $kwEsc = Database::escape('%' . $kw . '%');
        $groups[] = "(
            f.faculty LIKE '$kwEsc'
            OR br.branch LIKE '$kwEsc'
            OR EXISTS (
                SELECT 1 FROM programmes px
                LEFT JOIN intakes ix ON ix.programmes_programme_id = px.programme_id
                LEFT JOIN students_has_intakes shx ON shx.intakes_intake_id = ix.intake_id
                LEFT JOIN students stx ON stx.student_id = shx.students_student_id
                WHERE px.faculties_faculty_id = f.faculty_id
                  AND (
                        px.programme LIKE '$kwEsc'
                        OR ix.intake LIKE '$kwEsc'
                        OR stx.full_name LIKE '$kwEsc'
                        OR stx.name_with_initials LIKE '$kwEsc'
                        OR stx.nic LIKE '$kwEsc'
                        OR stx.passport LIKE '$kwEsc'
                        OR stx.mobile LIKE '$kwEsc'
                  )
            )
        )";
    }
    return implode(' AND ', $groups);
}

function buildProgrammeKeywordWhere(array $keywords): string
{
    if (empty($keywords)) {
        return '1=1';
    }
    $groups = [];
    foreach ($keywords as $kw) {
        $kwEsc = Database::escape('%' . $kw . '%');
        $groups[] = "(
            p.programme LIKE '$kwEsc'
            OR f.faculty LIKE '$kwEsc'
            OR br.branch LIKE '$kwEsc'
            OR EXISTS (
                SELECT 1 FROM intakes ix
                LEFT JOIN students_has_intakes shx ON shx.intakes_intake_id = ix.intake_id
                LEFT JOIN students stx ON stx.student_id = shx.students_student_id
                WHERE ix.programmes_programme_id = p.programme_id
                  AND (
                        ix.intake LIKE '$kwEsc'
                        OR stx.full_name LIKE '$kwEsc'
                        OR stx.name_with_initials LIKE '$kwEsc'
                        OR stx.nic LIKE '$kwEsc'
                        OR stx.passport LIKE '$kwEsc'
                        OR stx.mobile LIKE '$kwEsc'
                  )
            )
        )";
    }
    return implode(' AND ', $groups);
}

function buildIntakeKeywordWhere(array $keywords): string
{
    if (empty($keywords)) {
        return '1=1';
    }
    $groups = [];
    foreach ($keywords as $kw) {
        $kwEsc = Database::escape('%' . $kw . '%');
        $groups[] = "(
            i.intake LIKE '$kwEsc'
            OR p.programme LIKE '$kwEsc'
            OR f.faculty LIKE '$kwEsc'
            OR br.branch LIKE '$kwEsc'
            OR EXISTS (
                SELECT 1 FROM students_has_intakes shx
                INNER JOIN students stx ON stx.student_id = shx.students_student_id
                WHERE shx.intakes_intake_id = i.intake_id
                  AND (
                        stx.full_name LIKE '$kwEsc'
                        OR stx.name_with_initials LIKE '$kwEsc'
                        OR stx.nic LIKE '$kwEsc'
                        OR stx.passport LIKE '$kwEsc'
                        OR stx.mobile LIKE '$kwEsc'
                  )
            )
        )";
    }
    return implode(' AND ', $groups);
}

/**
 * Students are matched two ways per keyword:
 *   1) directly on the student's own fields (name, NIC, passport, mobile)
 *   2) OR via any of their enrollments' batch/programme/faculty/branch name
 * so that searching "Bachelor", "Batch 5", or a branch name surfaces the
 * students inside that batch/programme/branch — not just the batch itself.
 * This is a correlated EXISTS in WHERE (not a derived table in FROM), so
 * it doesn't hit the same "outer column not visible" restriction that
 * broke the earlier sample_students_json subquery.
 */
function buildStudentKeywordWhere(array $keywords): string
{
    if (empty($keywords)) {
        return '1=1';
    }
    $groups = [];
    foreach ($keywords as $kw) {
        $kwEsc = Database::escape('%' . $kw . '%');
        $groups[] = "(
            s.full_name LIKE '$kwEsc'
            OR s.name_with_initials LIKE '$kwEsc'
            OR s.nic LIKE '$kwEsc'
            OR s.passport LIKE '$kwEsc'
            OR s.mobile LIKE '$kwEsc'
            OR EXISTS (
                SELECT 1
                FROM students_has_intakes shx
                INNER JOIN intakes ix ON ix.intake_id = shx.intakes_intake_id
                INNER JOIN programmes px ON px.programme_id = ix.programmes_programme_id
                INNER JOIN faculties fx ON fx.faculty_id = px.faculties_faculty_id
                LEFT JOIN branches brx ON brx.branch_id = fx.branches_branch_id
                WHERE shx.students_student_id = s.student_id
                  AND (
                        ix.intake LIKE '$kwEsc'
                        OR px.programme LIKE '$kwEsc'
                        OR fx.faculty LIKE '$kwEsc'
                        OR brx.branch LIKE '$kwEsc'
                  )
            )
        )";
    }
    return implode(' AND ', $groups);
}

function decodeJsonArray(?string $json): array
{
    // Retained as a small utility in case you re-introduce JSON_ARRAYAGG
    // elsewhere (e.g. via a LATERAL join) — not used by the code below.
    if (!$json) {
        return [];
    }
    $arr = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

$results = [
    'branches'   => [],
    'faculties'  => [],
    'programmes' => [],
    'intakes'    => [],
    'students'   => [],
];
$counts = [
    'branches' => 0,
    'faculties' => 0,
    'programmes' => 0,
    'intakes' => 0,
    'students' => 0,
];
$hasMore = [
    'branches' => false,
    'faculties' => false,
    'programmes' => false,
    'intakes' => false,
    'students' => false,
];

$wantsAll = $type === 'all';

// --------------------------------------------------------------------------
// 3. BRANCHES
// --------------------------------------------------------------------------
if ($wantsAll || $type === 'branch') {
    $where = buildBranchKeywordWhere($keywords);

    $countRows = dbRows(Database::search("SELECT COUNT(*) AS c FROM branches b WHERE $where"));
    $counts['branches'] = (int) ($countRows[0]['c'] ?? 0);

    $sql = "
        SELECT
            b.branch_id,
            b.branch,
            b.date_added,
            (SELECT COUNT(*) FROM faculties f WHERE f.branches_branch_id = b.branch_id) AS faculty_count,
            (SELECT COUNT(*) FROM programmes p
                INNER JOIN faculties f2 ON f2.faculty_id = p.faculties_faculty_id
                WHERE f2.branches_branch_id = b.branch_id) AS programme_count,
            (SELECT COUNT(*) FROM intakes i
                INNER JOIN programmes p2 ON p2.programme_id = i.programmes_programme_id
                INNER JOIN faculties f3 ON f3.faculty_id = p2.faculties_faculty_id
                WHERE f3.branches_branch_id = b.branch_id) AS intake_count,
            (SELECT COUNT(*) FROM students_has_intakes sh
                INNER JOIN intakes i2 ON i2.intake_id = sh.intakes_intake_id
                INNER JOIN programmes p3 ON p3.programme_id = i2.programmes_programme_id
                INNER JOIN faculties f4 ON f4.faculty_id = p3.faculties_faculty_id
                WHERE f4.branches_branch_id = b.branch_id) AS student_count
        FROM branches b
        WHERE $where
        ORDER BY b.branch ASC
        LIMIT $limit OFFSET $offset
    ";
    $rows = dbRows(Database::search($sql));
    foreach ($rows as $r) {
        $results['branches'][] = [
            'type'            => 'branch',
            'branch_id'       => (int) $r['branch_id'],
            'branch'          => $r['branch'],
            'date_added'      => $r['date_added'],
            'faculty_count'   => (int) $r['faculty_count'],
            'programme_count' => (int) $r['programme_count'],
            'intake_count'    => (int) $r['intake_count'],
            'student_count'   => (int) $r['student_count'],
        ];
    }
    $hasMore['branches'] = $counts['branches'] > ($offset + count($rows));
}

// --------------------------------------------------------------------------
// 4. FACULTIES
// --------------------------------------------------------------------------
if ($wantsAll || $type === 'faculty') {
    $where = buildFacultyKeywordWhere($keywords);
    if ($branchId) {
        $where .= " AND br.branch_id = " . (int) $branchId;
    }

    $countRows = dbRows(Database::search("
        SELECT COUNT(*) AS c FROM faculties f
        LEFT JOIN branches br ON br.branch_id = f.branches_branch_id
        WHERE $where
    "));
    $counts['faculties'] = (int) ($countRows[0]['c'] ?? 0);

    $sql = "
        SELECT
            f.faculty_id,
            f.faculty,
            f.date_added,
            br.branch_id,
            br.branch,
            (SELECT COUNT(*) FROM programmes p WHERE p.faculties_faculty_id = f.faculty_id) AS programme_count,
            (SELECT COUNT(*) FROM intakes i
                INNER JOIN programmes p2 ON p2.programme_id = i.programmes_programme_id
                WHERE p2.faculties_faculty_id = f.faculty_id) AS intake_count,
            (SELECT COUNT(*) FROM students_has_intakes sh
                INNER JOIN intakes i2 ON i2.intake_id = sh.intakes_intake_id
                INNER JOIN programmes p3 ON p3.programme_id = i2.programmes_programme_id
                WHERE p3.faculties_faculty_id = f.faculty_id) AS student_count
        FROM faculties f
        LEFT JOIN branches br ON br.branch_id = f.branches_branch_id
        WHERE $where
        ORDER BY f.faculty ASC
        LIMIT $limit OFFSET $offset
    ";
    $rows = dbRows(Database::search($sql));
    foreach ($rows as $r) {
        $results['faculties'][] = [
            'type'            => 'faculty',
            'faculty_id'      => (int) $r['faculty_id'],
            'faculty'         => $r['faculty'],
            'branch_id'       => $r['branch_id'] !== null ? (int) $r['branch_id'] : null,
            'branch'          => $r['branch'],
            'date_added'      => $r['date_added'],
            'programme_count' => (int) $r['programme_count'],
            'intake_count'    => (int) $r['intake_count'],
            'student_count'   => (int) $r['student_count'],
        ];
    }
    $hasMore['faculties'] = $counts['faculties'] > ($offset + count($rows));
}

// --------------------------------------------------------------------------
// 5. PROGRAMMES
// --------------------------------------------------------------------------
if ($wantsAll || $type === 'programme') {
    $where = buildProgrammeKeywordWhere($keywords);
    if ($branchId) {
        $where .= " AND br.branch_id = " . (int) $branchId;
    }

    $countRows = dbRows(Database::search("
        SELECT COUNT(*) AS c FROM programmes p
        INNER JOIN faculties f ON f.faculty_id = p.faculties_faculty_id
        LEFT JOIN branches br ON br.branch_id = f.branches_branch_id
        WHERE $where
    "));
    $counts['programmes'] = (int) ($countRows[0]['c'] ?? 0);

    $sql = "
        SELECT
            p.programme_id,
            p.programme,
            p.date_added,
            f.faculty_id,
            f.faculty,
            br.branch_id,
            br.branch,
            (SELECT COUNT(*) FROM intakes i WHERE i.programmes_programme_id = p.programme_id) AS intake_count,
            (SELECT COUNT(*) FROM students_has_intakes sh
                INNER JOIN intakes i2 ON i2.intake_id = sh.intakes_intake_id
                WHERE i2.programmes_programme_id = p.programme_id) AS student_count
        FROM programmes p
        INNER JOIN faculties f ON f.faculty_id = p.faculties_faculty_id
        LEFT JOIN branches br ON br.branch_id = f.branches_branch_id
        WHERE $where
        ORDER BY p.programme ASC
        LIMIT $limit OFFSET $offset
    ";
    $rows = dbRows(Database::search($sql));
    foreach ($rows as $r) {
        $results['programmes'][] = [
            'type'          => 'programme',
            'programme_id'  => (int) $r['programme_id'],
            'programme'     => $r['programme'],
            'faculty_id'    => (int) $r['faculty_id'],
            'faculty'       => $r['faculty'],
            'branch_id'     => $r['branch_id'] !== null ? (int) $r['branch_id'] : null,
            'branch'        => $r['branch'],
            'date_added'    => $r['date_added'],
            'intake_count'  => (int) $r['intake_count'],
            'student_count' => (int) $r['student_count'],
        ];
    }
    $hasMore['programmes'] = $counts['programmes'] > ($offset + count($rows));
}

// --------------------------------------------------------------------------
// 6. INTAKES (BATCHES) — the client's headline example ("Batch 5")
// --------------------------------------------------------------------------
if ($wantsAll || $type === 'intake') {
    $where = buildIntakeKeywordWhere($keywords);
    if ($branchId) {
        $where .= " AND br.branch_id = " . (int) $branchId;
    }
    if ($programmeId) {
        $where .= " AND p.programme_id = " . (int) $programmeId;
    }

    $countRows = dbRows(Database::search("
        SELECT COUNT(*) AS c FROM intakes i
        INNER JOIN programmes p ON p.programme_id = i.programmes_programme_id
        INNER JOIN faculties f ON f.faculty_id = p.faculties_faculty_id
        LEFT JOIN branches br ON br.branch_id = f.branches_branch_id
        WHERE $where
    "));
    $counts['intakes'] = (int) ($countRows[0]['c'] ?? 0);

    $sql = "
        SELECT
            i.intake_id,
            i.intake,
            i.date_added,
            p.programme_id,
            p.programme,
            f.faculty_id,
            f.faculty,
            br.branch_id,
            br.branch,
            (SELECT COUNT(*) FROM students_has_intakes sh
                WHERE sh.intakes_intake_id = i.intake_id) AS student_count
        FROM intakes i
        INNER JOIN programmes p ON p.programme_id = i.programmes_programme_id
        INNER JOIN faculties f ON f.faculty_id = p.faculties_faculty_id
        LEFT JOIN branches br ON br.branch_id = f.branches_branch_id
        WHERE $where
        ORDER BY i.intake ASC
        LIMIT $limit OFFSET $offset
    ";
    $rows = dbRows(Database::search($sql));

    // ---- Sample students preview (batched, NOT a correlated subquery) ----
    // MySQL does not allow a derived table (a subquery in FROM) to reference
    // a column from the outer query — `FROM (SELECT ... WHERE x = i.intake_id) x`
    // fails with "Unknown column 'i.intake_id'" because that correlation is
    // invalid outside of MySQL 8.0.14+'s LATERAL joins. Fetching the previews
    // in one batched follow-up query sidesteps that entirely and is portable
    // across any MySQL 8 host.
    $sampleStudentsByIntake = [];
    $intakeIds = array_column($rows, 'intake_id');
    if (!empty($intakeIds)) {
        $idsEsc = implode(',', array_map('intval', $intakeIds));
        $sampleSql = "
            SELECT sh2.intakes_intake_id AS intake_id, st.full_name, sh2.addmission_date
            FROM students_has_intakes sh2
            INNER JOIN students st ON st.student_id = sh2.students_student_id
            WHERE sh2.intakes_intake_id IN ($idsEsc)
            ORDER BY sh2.addmission_date DESC
        ";
        $sampleRows = dbRows(Database::search($sampleSql));
        foreach ($sampleRows as $sr) {
            $iid = (int) $sr['intake_id'];
            if (!isset($sampleStudentsByIntake[$iid])) {
                $sampleStudentsByIntake[$iid] = [];
            }
            // Cap at 5 per batch, same limit the old SQL-side LIMIT 5 gave.
            if (count($sampleStudentsByIntake[$iid]) < 5) {
                $sampleStudentsByIntake[$iid][] = $sr['full_name'];
            }
        }
    }

    foreach ($rows as $r) {
        $results['intakes'][] = [
            'type'            => 'intake',
            'intake_id'       => (int) $r['intake_id'],
            'intake'          => $r['intake'],
            'programme_id'    => (int) $r['programme_id'],
            'programme'       => $r['programme'],
            'faculty_id'      => (int) $r['faculty_id'],
            'faculty'         => $r['faculty'],
            'branch_id'       => $r['branch_id'] !== null ? (int) $r['branch_id'] : null,
            'branch'          => $r['branch'],
            'date_added'      => $r['date_added'],
            'student_count'   => (int) $r['student_count'],
            'sample_students' => $sampleStudentsByIntake[(int) $r['intake_id']] ?? [],
        ];
    }
    $hasMore['intakes'] = $counts['intakes'] > ($offset + count($rows));
}

// --------------------------------------------------------------------------
// 7. STUDENTS
//    Also supports: type=student&intake_id=X&mode=full  -> full roster of
//    one batch (used by the "View all N students" button on a batch row).
// --------------------------------------------------------------------------
if ($wantsAll || $type === 'student') {
    $where = buildStudentKeywordWhere($keywords);

    $joinIntakeFilter = '';
    if ($intakeId) {
        // Narrow to students enrolled in one specific batch.
        $joinIntakeFilter = "
            INNER JOIN students_has_intakes shf ON shf.students_student_id = s.student_id
                AND shf.intakes_intake_id = " . (int) $intakeId . "
        ";
    }

    $countRows = dbRows(Database::search("
        SELECT COUNT(DISTINCT s.student_id) AS c
        FROM students s
        $joinIntakeFilter
        WHERE $where
    "));
    $counts['students'] = (int) ($countRows[0]['c'] ?? 0);

    // Latest enrollment per student (branch/faculty/programme/batch context)
    $sql = "
        SELECT
            s.student_id,
            s.full_name,
            s.name_with_initials,
            s.mobile,
            s.nic,
            s.passport,
            s.date_added,
            le.intake_id,
            le.intake,
            le.programme_id,
            le.programme,
            le.faculty_id,
            le.faculty,
            le.branch_id,
            le.branch,
            le.student_reg_id,
            (SELECT COUNT(*) FROM students_has_intakes sh3
                WHERE sh3.students_student_id = s.student_id) AS batch_count
        FROM students s
        $joinIntakeFilter
        LEFT JOIN (
            SELECT
                ranked.students_student_id, ranked.intake_id, ranked.intake,
                ranked.programme_id, ranked.programme, ranked.faculty_id, ranked.faculty,
                ranked.branch_id, ranked.branch, ranked.student_reg_id
            FROM (
                SELECT
                    sh.students_student_id,
                    sh.student_id AS student_reg_id,
                    sh.addmission_date,
                    i.intake_id, i.intake,
                    p.programme_id, p.programme,
                    f.faculty_id, f.faculty,
                    brr.branch_id, brr.branch,
                    ROW_NUMBER() OVER (
                        PARTITION BY sh.students_student_id
                        ORDER BY sh.addmission_date DESC
                    ) AS rn
                FROM students_has_intakes sh
                INNER JOIN intakes i ON i.intake_id = sh.intakes_intake_id
                INNER JOIN programmes p ON p.programme_id = i.programmes_programme_id
                INNER JOIN faculties f ON f.faculty_id = p.faculties_faculty_id
                LEFT JOIN branches brr ON brr.branch_id = f.branches_branch_id
            ) ranked
            WHERE ranked.rn = 1
        ) le ON le.students_student_id = s.student_id
        WHERE $where
        GROUP BY s.student_id
        ORDER BY s.full_name ASC
        LIMIT $limit OFFSET $offset
    ";
    $rows = dbRows(Database::search($sql));
    foreach ($rows as $r) {
        $results['students'][] = [
            'type'               => 'student',
            'student_id'         => (int) $r['student_id'],
            'full_name'          => $r['full_name'],
            'name_with_initials' => $r['name_with_initials'],
            'mobile'             => $r['mobile'],
            'nic'                => $r['nic'],
            'passport'           => $r['passport'],
            'student_reg_id'     => $r['student_reg_id'],
            'intake_id'          => $r['intake_id'] !== null ? (int) $r['intake_id'] : null,
            'intake'             => $r['intake'],
            'programme_id'       => $r['programme_id'] !== null ? (int) $r['programme_id'] : null,
            'programme'          => $r['programme'],
            'faculty_id'         => $r['faculty_id'] !== null ? (int) $r['faculty_id'] : null,
            'faculty'            => $r['faculty'],
            'branch_id'          => $r['branch_id'] !== null ? (int) $r['branch_id'] : null,
            'branch'             => $r['branch'],
            'batch_count'        => (int) $r['batch_count'],
        ];
    }
    $hasMore['students'] = $counts['students'] > ($offset + count($rows));
}

// --------------------------------------------------------------------------
// 8. Respond
// --------------------------------------------------------------------------
echo json_encode([
    'success'  => true,
    'query'    => $rawQuery,
    'type'     => $type,
    'mode'     => $mode,
    'page'     => $page,
    'counts'   => $counts,
    'results'  => $results,
    'has_more' => $hasMore,
], JSON_UNESCAPED_UNICODE);
