<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";

header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Your session has expired. Please log in again.']);
    exit;
}

$sessionUserRole = SessionUser::getCurrentUserRole();
if ($sessionUserRole == null) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have access to this page.']);
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

$counselorUserId = (int) $sessionUserObject['user_id'];

$input = json_decode(file_get_contents('php://input'), true) ?: [];

if (SessionUser::ifUserIsPro($sessionUserRole) && !empty($input['counselor_id'])) {
    $counselorUserId = (int) $input['counselor_id'];
}

// Branch = ID. Faculty / Programme / Intake / Subject = NAME (so the same
// name across different branches is matched together). "0" / "" = all.
function nameFilter($value)
{
    if (!isset($value)) return '';
    $value = trim((string) $value);
    return ($value === '' || $value === '0') ? '' : $value;
}

$branchId    = isset($input['branch_id']) ? (int) $input['branch_id'] : 0;   // 0 = all
$facultyName   = nameFilter($input['faculty'] ?? null);
$programmeName = nameFilter($input['programme'] ?? null);
$intakeName    = nameFilter($input['intake'] ?? null);
$subjectName   = nameFilter($input['subject'] ?? null);
$fixedIntakeId = isset($input['intake_id']) ? (int) $input['intake_id'] : 0;  // exact id (?intake-id=)
$startDate   = isset($input['start_date']) ? $input['start_date'] : null;
$endDate     = isset($input['end_date']) ? $input['end_date'] : null;

const AT_RISK_STREAK_THRESHOLD = 3;

function isValidDate($date)
{
    if (!$date) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

if (!isValidDate($startDate) || !isValidDate($endDate) || $startDate > $endDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Please select a valid date range.']);
    exit;
}

$startObj = new DateTime($startDate);
$endObj   = new DateTime($endDate);
$dayDiff  = (int) $endObj->diff($startObj)->format('%a');
if ($dayDiff > 366) {
    http_response_code(400);
    echo json_encode(['error' => 'Please select a range of one year or less.']);
    exit;
}

$startDateEsc = Database::escape($startDate);
$endDateEsc   = Database::escape($endDate);

$timezone = new DateTimeZone("Asia/Colombo");
$now = new DateTime("now", $timezone);

function emptyResult($totalStudents = 0)
{
    echo json_encode([
        'total_students'     => $totalStudents,
        'lectures_held'      => 0,
        'average_attendance' => 0,
        'status_breakdown'   => ['PRESENT' => 0, 'ABSENT' => 0, 'HALF_VERIFY' => 0, 'WATCHED' => 0],
        'trend'              => [],
        'students'           => [],
        'at_risk'            => [],
    ]);
    exit;
}

// ----------------------------------------------------------------------
// 1. Roster. Branch filters by id; faculty/programme/intake filter by
//    name, so every same-named record across branches is included.
// ----------------------------------------------------------------------
$rosterQuery = "
    SELECT
        shi.students_has_intakes_id AS shi_id,
        shi.student_id AS sId,
        s.student_id AS student_pk,
        ld.first_name,
        ld.last_name,
        title.title,
        s.profile_img,
        i.intake_id,
        i.intake,
        p.programme_id,
        p.programme,
        f.faculty_id,
        f.faculty
    FROM students_has_intakes shi
    INNER JOIN students s ON s.student_id = shi.students_student_id
    INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
    INNER JOIN title ON s.title_title_id = title.title_id
    INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
    INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
    INNER JOIN faculties f ON p.faculties_faculty_id = f.faculty_id
    INNER JOIN branches b ON f.branches_branch_id = b.branch_id
    WHERE shi.status_status_id != '2'
";

if ($branchId > 0) {
    $rosterQuery .= " AND b.branch_id = '" . Database::escape($branchId) . "'";
}
if ($facultyName !== '') {
    $rosterQuery .= " AND f.faculty = '" . Database::escape($facultyName) . "'";
}
if ($programmeName !== '') {
    $rosterQuery .= " AND p.programme = '" . Database::escape($programmeName) . "'";
}
if ($intakeName !== '') {
    $rosterQuery .= " AND i.intake = '" . Database::escape($intakeName) . "'";
}
if ($fixedIntakeId > 0) {
    $rosterQuery .= " AND i.intake_id = '" . Database::escape($fixedIntakeId) . "'";
}

$studentRows = Database::search($rosterQuery)->fetch_all(MYSQLI_ASSOC);

if (empty($studentRows)) {
    emptyResult();
}

$studentMeta = [];
foreach ($studentRows as $row) {
    $studentMeta[$row['student_pk']] = [
        'sId'         => $row['sId'] == null ? "-" : $row['sId'],
        'first_name'  => $row['first_name'],
        'last_name'   => $row['last_name'],
        'title'       => $row['title'],
        'profile_img' => (!empty($row['profile_img']) && file_exists('../assets/imgs/students/' . $row['profile_img'])) ? $row['profile_img'] : null,
        'intake_id'   => $row['intake_id'],
        'intake'      => $row['intake'],
        'programme'   => $row['programme'],
        'faculty'     => $row['faculty'],
    ];
}
$totalStudents = count($studentMeta);

// ----------------------------------------------------------------------
// 2. Lectures for every intake in the roster, optionally narrowed to a
//    subject NAME (matches that subject in every intake/branch).
// ----------------------------------------------------------------------
$rosterIntakeIds = array_values(array_unique(array_column($studentMeta, 'intake_id')));
$rosterIntakeIdsEsc = implode(',', array_map('intval', $rosterIntakeIds));

$lecturesQuery = "
    SELECT
        tt.time_table_id,
        tt.date,
        tt.start_time,
        tt.end_time,
        tt.lecture_type_lecture_type_id AS lecture_type_id,
        s.subject_id,
        s.subject,
        s.subject_type_subject_type_id AS subject_type_id,
        l.intakes_intake_id AS intake_id
    FROM time_table tt
    INNER JOIN lecturers lec ON tt.lecturers_lecturers_id = lec.lecturers_id
    INNER JOIN subjects s ON lec.subjects_subject_id = s.subject_id
    INNER JOIN levels l ON s.levels_level_id = l.level_id
    WHERE l.intakes_intake_id IN (" . $rosterIntakeIdsEsc . ")
      AND tt.time_table_status_time_table_status_id = 1
      AND tt.date BETWEEN '" . $startDateEsc . "' AND '" . $endDateEsc . "'
";
if ($subjectName !== '') {
    $lecturesQuery .= " AND s.subject = '" . Database::escape($subjectName) . "'";
}
$lecturesQuery .= " ORDER BY tt.date ASC, tt.start_time ASC";

$lectureRows = Database::search($lecturesQuery)->fetch_all(MYSQLI_ASSOC);

if (empty($lectureRows)) {
    emptyResult($totalStudents);
}

$lectureIds = array_column($lectureRows, 'time_table_id');
$lectureIdsEsc = implode(',', array_map('intval', $lectureIds));

// ----------------------------------------------------------------------
// 3. Elective membership (type-2 subjects are opt-in).
// ----------------------------------------------------------------------
$electiveSubjectIds = array_values(array_unique(array_filter(array_map(function ($lr) {
    return $lr['subject_type_id'] == 2 ? $lr['subject_id'] : null;
}, $lectureRows))));

$electiveMembership = []; // [subject_id][student_pk] => true
if (!empty($electiveSubjectIds)) {
    $electiveIdsEsc = implode(',', array_map('intval', $electiveSubjectIds));
    $membershipQuery = "
        SELECT students_student_id AS student_pk, subjects_subject_id AS subject_id
        FROM students_has_subjects
        WHERE subjects_subject_id IN (" . $electiveIdsEsc . ")
          AND status_status_id = '1'
    ";
    $membershipRows = Database::search($membershipQuery)->fetch_all(MYSQLI_ASSOC);
    foreach ($membershipRows as $row) {
        $electiveMembership[$row['subject_id']][$row['student_pk']] = true;
    }
}

// ----------------------------------------------------------------------
// 4. Attendance records. Status IDs: 1 Half Verify, 2 Present, 3 Absent, 4 Watched.
// ----------------------------------------------------------------------
$attendanceQuery = "
    SELECT students_student_id AS student_pk, time_table_time_table_id AS tt_id, attendance_status_attendance_status_id AS status_id
    FROM attendance
    WHERE time_table_time_table_id IN (" . $lectureIdsEsc . ")
";
$attendanceRows = Database::search($attendanceQuery)->fetch_all(MYSQLI_ASSOC);

$attendanceMap = [];
foreach ($attendanceRows as $row) {
    $attendanceMap[$row['student_pk']][$row['tt_id']] = (int) $row['status_id'];
}

// ----------------------------------------------------------------------
// 5. Walk every (lecture x eligible student) pair.
// ----------------------------------------------------------------------
$statusBreakdown = ['PRESENT' => 0, 'ABSENT' => 0, 'HALF_VERIFY' => 0, 'WATCHED' => 0];
$dailyTotals = [];
$perStudentRecords = [];
$perStudentTotals = [];
$totalHeldLectures = 0;

foreach ($lectureRows as $lecture) {
    $ttId = $lecture['time_table_id'];
    $lectureEnd = new DateTime($lecture['date'] . ' ' . $lecture['end_time'], $timezone);
    if ($now < $lectureEnd) {
        continue;
    }

    $totalHeldLectures++;

    $lectureIntakeId = $lecture['intake_id'];
    $lectureSubjectId = $lecture['subject_id'];
    $isElective = ($lecture['subject_type_id'] == 2);

    foreach ($studentMeta as $studentPk => $meta) {
        if ($meta['intake_id'] != $lectureIntakeId) {
            continue;
        }
        if ($isElective && empty($electiveMembership[$lectureSubjectId][$studentPk])) {
            continue;
        }

        $statusId = $attendanceMap[$studentPk][$ttId] ?? null;

        if ($statusId === 2) {
            $outcome = 'PRESENT';
        } elseif ($statusId === 4) {
            $outcome = 'WATCHED';
        } elseif ($statusId === 1) {
            $outcome = 'HALF_VERIFY';
        } else {
            $outcome = 'ABSENT'; // status 3 or no record on an ended lecture
        }

        $statusBreakdown[$outcome]++;

        if (!isset($dailyTotals[$lecture['date']])) {
            $dailyTotals[$lecture['date']] = ['held' => 0, 'present_equivalent' => 0];
        }
        $dailyTotals[$lecture['date']]['held']++;
        if ($outcome === 'PRESENT' || $outcome === 'WATCHED') {
            $dailyTotals[$lecture['date']]['present_equivalent']++;
        }

        if (!isset($perStudentTotals[$studentPk])) {
            $perStudentTotals[$studentPk] = ['held' => 0, 'present' => 0, 'absent' => 0, 'half_verify' => 0, 'watched' => 0];
        }
        $perStudentTotals[$studentPk]['held']++;
        if ($outcome === 'PRESENT') $perStudentTotals[$studentPk]['present']++;
        elseif ($outcome === 'WATCHED') $perStudentTotals[$studentPk]['watched']++;
        elseif ($outcome === 'HALF_VERIFY') $perStudentTotals[$studentPk]['half_verify']++;
        else $perStudentTotals[$studentPk]['absent']++;

        $perStudentRecords[$studentPk][] = [
            'date'    => $lecture['date'],
            'time'    => $lecture['start_time'],
            'tt_id'   => $ttId,
            'outcome' => $outcome,
        ];
    }
}

ksort($dailyTotals);

// ----------------------------------------------------------------------
// 6. Daily trend.
// ----------------------------------------------------------------------
$trend = [];
foreach ($dailyTotals as $date => $counts) {
    $pct = $counts['held'] > 0 ? round(($counts['present_equivalent'] / $counts['held']) * 100, 1) : 0;
    $trend[] = [
        'date'           => $date,
        'date_label'     => date('d M', strtotime($date)),
        'attendance_pct' => $pct,
    ];
}

// ----------------------------------------------------------------------
// 7. Per-student summary + consecutive-absence streak.
// ----------------------------------------------------------------------
$students = [];
$atRisk = [];

foreach ($studentMeta as $studentPk => $meta) {
    $totals = $perStudentTotals[$studentPk] ?? ['held' => 0, 'present' => 0, 'absent' => 0, 'half_verify' => 0, 'watched' => 0];
    $records = $perStudentRecords[$studentPk] ?? [];

    $presentEquivalent = $totals['present'] + $totals['watched'];
    $attendancePct = $totals['held'] > 0 ? round(($presentEquivalent / $totals['held']) * 100, 1) : 0;

    $streak = 0;
    $lastAttendedDate = null;
    for ($i = count($records) - 1; $i >= 0; $i--) {
        if ($records[$i]['outcome'] === 'ABSENT') {
            $streak++;
        } else {
            $lastAttendedDate = $records[$i]['date'];
            break;
        }
    }

    // Students with no eligible lectures at all in this filter are skipped
    // from the table (nothing to report on).
    if ($totals['held'] === 0) {
        continue;
    }

    $studentRow = [
        'sId'              => $meta['sId'],
        'first_name'       => $meta['first_name'],
        'last_name'        => $meta['last_name'],
        'title'            => $meta['title'],
        'profile_img'      => $meta['profile_img'],
        'faculty'          => $meta['faculty'],
        'programme'        => $meta['programme'],
        'intake'           => $meta['intake'],
        'lectures_held'    => $totals['held'],
        'present_days'     => $totals['present'],
        'watched_days'     => $totals['watched'],
        'half_verify_days' => $totals['half_verify'],
        'absent_days'      => $totals['absent'],
        'attendance_pct'   => $attendancePct,
        'absence_streak'   => $streak,
    ];

    $students[] = $studentRow;

    if ($streak >= AT_RISK_STREAK_THRESHOLD) {
        $atRisk[] = array_merge($studentRow, ['last_attended_date' => $lastAttendedDate]);
    }
}

usort($students, fn($a, $b) => $a['attendance_pct'] <=> $b['attendance_pct']);
usort($atRisk, fn($a, $b) => $b['absence_streak'] <=> $a['absence_streak']);

// ----------------------------------------------------------------------
// 8. Average attendance = mean of the daily trend %s.
// ----------------------------------------------------------------------
$averageAttendance = 0;
if (!empty($trend)) {
    $averageAttendance = round(array_sum(array_column($trend, 'attendance_pct')) / count($trend), 1);
}

echo json_encode([
    'total_students'     => $totalStudents,
    'lectures_held'      => $totalHeldLectures,
    'average_attendance' => $averageAttendance,
    'status_breakdown'   => $statusBreakdown,
    'trend'              => $trend,
    'students'           => $students,
    'at_risk'            => $atRisk,
]);
