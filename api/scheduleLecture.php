<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");

/* ==========================================================================
 * REQUIRES a migration before this will work — see chat for the full
 * ALTER TABLE. Summary:
 *   - `time_table`.`lecturers_lecturers_id` must become NULLable
 *     (it's subject-bound, so it can't be set for a broad-scope row).
 *   - `time_table`.`broad_lecturer_user_id` (new, nullable INT, FK ->
 *     users.user_id) stores the lecturer's user id for broad-scope rows.
 *
 * WILDCARD_VALUE ("-1") means "entire {parent}" for branch/faculty/
 * programme/intake — these map onto `time_table`'s existing nullable
 * scope columns. Level and Subject have no dedicated storage: wildcarding
 * either of them collapses to storing at the Intake scope (subject left
 * NULL) since `time_table` has no `levels_level_id` column.
 *
 * ASSUMPTIONS flagged inline with "// ASSUMED" — verify against your
 * actual schema (table/column names for faculties/programmes/intakes).
 * ========================================================================== */

const WILDCARD_VALUE = '-1';

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 2))) {
                $status->error = "You do not have permission to schedule a lecture";
            } else {

                $branch = isset($_POST['branch']) ? trim($_POST['branch']) : '0';
                $faculty = isset($_POST['faculty']) ? trim($_POST['faculty']) : '0';
                $programme_id = isset($_POST['programme_id']) ? trim($_POST['programme_id']) : '0';
                $intake_id = isset($_POST['intake_id']) ? trim($_POST['intake_id']) : '0';
                $level_id = isset($_POST['level_id']) ? trim($_POST['level_id']) : '0';
                $subject_id = isset($_POST['subject_id']) ? trim($_POST['subject_id']) : '0';
                $lecturer_id = isset($_POST['lecturer_id']) ? trim($_POST['lecturer_id']) : '0';
                $broad_lecturer_user_id = isset($_POST['broad_lecturer_user_id']) ? trim($_POST['broad_lecturer_user_id']) : '0';
                $lecture_date = isset($_POST['lecture-date']) ? trim($_POST['lecture-date']) : '';
                $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
                $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $physical = isset($_POST['physical']) && $_POST['physical'] === 'true' ? true : false;
                $online = isset($_POST['online']) && $_POST['online'] === 'true' ? true : false;
                $hybrid = isset($_POST['hybrid']) && $_POST['hybrid'] === 'true' ? true : false;
                $link = isset($_POST['link']) ? trim($_POST['link']) : '';
                $one_time = isset($_POST['one_time']) && $_POST['one_time'] === 'true' ? true : false;
                $recurring = isset($_POST['recurring']) && $_POST['recurring'] === 'true' ? true : false;
                $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
                $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
                $days = isset($_POST['days']) ? trim($_POST['days']) : [];
                $skip_poya_days = isset($_POST['skip_poya_days']) && $_POST['skip_poya_days'] === 'true' ? true : false;
                $skip_holidays = isset($_POST['skip_holidays']) && $_POST['skip_holidays'] === 'true' ? true : false;

                // =========================================================
                // Resolve the scope chain: branch -> faculty -> programme -> intake.
                // Each of these maps onto a real, nullable column on `time_table`.
                // The moment a wildcard is hit, everything after it is ignored
                // (not required, not stored) regardless of what was POSTed —
                // this is defensive against a stale/tampered form.
                // =========================================================
                $scopeChain = [
                    'branch'    => ['value' => $branch,       'column' => 'branches_branch_id',     'label' => 'branch',    'table' => 'branches',   'pk' => 'branch_id',    'name' => 'branch'],
                    'faculty'   => ['value' => $faculty,      'column' => 'faculties_faculty_id',   'label' => 'faculty',   'table' => 'faculties',  'pk' => 'faculty_id',   'name' => 'faculty'], // ASSUMED table/column names
                    'programme' => ['value' => $programme_id, 'column' => 'programmes_programme_id', 'label' => 'programme', 'table' => 'programmes', 'pk' => 'programme_id', 'name' => 'programme'], // ASSUMED
                    'intake'    => ['value' => $intake_id,    'column' => 'intakes_intake_id',      'label' => 'intake',    'table' => 'intakes',    'pk' => 'intake_id',    'name' => 'intake'], // ASSUMED
                ];

                $wildcardHit = false;
                $resolvedScope = []; // column => id, for concrete levels only
                $deepestScopeName = null; // human-readable label for lecture titles

                foreach ($scopeChain as $key => $scope) {
                    if ($wildcardHit) {
                        continue; // everything after a wildcard is skipped entirely
                    }
                    if ($scope['value'] === WILDCARD_VALUE) {
                        $wildcardHit = true;
                        continue;
                    }
                    if ($scope['value'] == 0 || $scope['value'] === '') {
                        $status->$key = "Please select a {$scope['label']}";
                    } else {
                        $resolvedScope[$scope['column']] = $scope['value'];
                        $nameRow = Database::search("SELECT `{$scope['name']}` AS name FROM `{$scope['table']}` WHERE `{$scope['pk']}` = '" . Database::escape($scope['value']) . "'");
                        $nameData = $nameRow->fetch_assoc();
                        $deepestScopeName = $nameData ? $nameData['name'] : $scope['label'];
                    }
                }

                if ($sessionUserRole == "academic" && isset($resolvedScope['intakes_intake_id'])) {
                    $isValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` 
                                                            INNER JOIN `users_has_intakes` ON `intakes`.`intake_id` = `users_has_intakes`.`intakes_intake_id`
                                                            WHERE `intake_id` = '" . $resolvedScope['intakes_intake_id'] . "' AND `users_has_intakes`.`users_user_id` = '" . $sessionUserObject["user_id"] . "'");
                    $row = $isValidIntake->fetch_assoc();
                    if ($row['count'] == 0) {
                        $status->intake = 'You do not have access to this intake shedule lecture';
                    }
                }
                // NOTE: academic users choosing a wildcard ABOVE their permitted
                // intake (e.g. entire programme/faculty/branch) currently bypass
                // this per-intake access check entirely, since there's no single
                // intake to check against. Decide whether that's acceptable or
                // whether wildcards above intake should be blocked for academics.

                // =========================================================
                // Level (UI-only filter, no storage column) + Subject.
                // Only evaluated if the chain above didn't already wildcard —
                // otherwise level/subject are meaningless and skipped.
                // =========================================================
                $subjectResolved = false;

                if (!$wildcardHit) {
                    if ($level_id === WILDCARD_VALUE) {
                        // "Entire Intake" via the Level selector — collapses to the
                        // same stored scope as wildcarding Intake itself.
                    } elseif ($level_id == 0 || $level_id === '') {
                        $status->level = 'Please select a level';
                    } else {
                        if ($subject_id === WILDCARD_VALUE) {
                            // "Entire Level" — no `levels_level_id` column exists on
                            // time_table, so this also collapses to Intake-scope
                            // storage. Level granularity is lost here; add a
                            // `levels_level_id` column if you need to preserve it.
                        } elseif ($subject_id == 0 || $subject_id === '') {
                            $status->subject = 'Please select a subject';
                        } else {
                            if ($lecturer_id == 0) {
                                $status->lecturer = 'Please select a lecturer';
                            } else {
                                $isValidLecturer = Database::search("SELECT * FROM `lecturers` WHERE `lecturers_id` = '" . Database::escape($lecturer_id) . "' AND `subjects_subject_id` = '" . Database::escape($subject_id) . "'");
                                if ($isValidLecturer->num_rows == 0) {
                                    $status->lecturer = 'This lecturer is not assigned to this subject';
                                } else {
                                    $lecturerData = $isValidLecturer->fetch_assoc();
                                    $resolvedScope['subjects_subject_id'] = $subject_id;
                                    $subjectResolved = true;

                                    $subjectRow = Database::search("SELECT `subject` AS name FROM `subjects` WHERE `subject_id` = '" . Database::escape($subject_id) . "'");
                                    $subjectData = $subjectRow->fetch_assoc();
                                    $deepestScopeName = $subjectData ? $subjectData['name'] : $deepestScopeName;
                                }
                            }
                        }
                    }
                }

                // =========================================================
                // Broad-scope lecturer resolution — whenever we did NOT land
                // on a concrete subject (wildcard hit anywhere in the chain,
                // or Level/Subject wildcarded).
                // =========================================================
                $lecturerUserIdForConflictCheck = null;

                if (!$subjectResolved) {
                    if ($broad_lecturer_user_id == 0) {
                        $status->lecturer = 'Please select a lecturer';
                    } else {
                        // Light validation: confirm this person has at least one
                        // lecturer record somewhere in the system.
                        $isValidBroadLecturer = Database::search("SELECT DISTINCT `users_user_id` FROM `lecturers` WHERE `users_user_id` = '" . Database::escape($broad_lecturer_user_id) . "'");
                        if ($isValidBroadLecturer->num_rows == 0) {
                            $status->lecturer = 'Invalid lecturer selected';
                        } else {
                            $lecturerUserIdForConflictCheck = $broad_lecturer_user_id;
                        }
                    }
                } else {
                    $lecturerUserIdForConflictCheck = $lecturerData['users_user_id'];
                }

                if ($one_time && empty($lecture_date)) {
                    $status->date = 'Please select a lecture date';
                }
                if (empty($start_time)) {
                    $status->start_time = 'Please select a start time';
                }
                if (empty($end_time)) {
                    $status->end_time = 'Please select an end time';
                }
                if ($one_time && !empty($lecture_date) && !empty($start_time)) {
                    if (!checkIfFutureDateTime($lecture_date, $start_time)) {
                        $status->start_time = 'The selected date and start time is in the past';
                    }
                }
                if ($one_time && !empty($lecture_date) && !empty($end_time)) {
                    if (!checkIfFutureDateTime($lecture_date, $end_time)) {
                        $status->end_time = 'The selected date and end time is in the past';
                    }
                }
                if (($online || $hybrid) && empty($link)) {
                    $status->link = 'Please enter a link';
                }

                if ($one_time && empty($title)) {
                    $status->title = 'Please enter a title';
                } else if (strlen($title) > 50) {
                    $status->title = 'Title must be less than 50 characters';
                }

                $tutes = array();

                if (isset($_FILES["tutes"])) {
                    $allowed_tute_extensions = array(
                        "application/pdf",
                        "application/msword",
                        "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    );

                    foreach ($_FILES["tutes"]["name"] as $key => $tute_name) {
                        $tute_ex = $_FILES["tutes"]["type"][$key];

                        if (!in_array($tute_ex, $allowed_tute_extensions)) {
                            $status->tute[] = "File '$tute_name' is not a valid type. Only PDF, DOC, and DOCX files are allowed.";
                        } else if (strlen($tute_name) > 45) {
                            $status->tute[] = "File '$tute_name' exceeds the maximum allowed character limit of 45.";
                        } else {
                            $new_tute_extension = '';

                            switch ($tute_ex) {
                                case "application/pdf":
                                    $new_tute_extension = ".pdf";
                                    break;
                                case "application/msword":
                                    $new_tute_extension = ".doc";
                                    break;
                                case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
                                    $new_tute_extension = ".docx";
                                    break;
                            }

                            if ($_FILES["tutes"]["error"][$key] === 0) {
                                if ($_FILES["tutes"]["size"][$key] <= 10 * 1024 * 1024) {
                                    $tute = uniqid() . $new_tute_extension;

                                    array_push($tutes, $tute);
                                } else {
                                    $status->tute[] = "File '$tute_name' exceeds the maximum allowed size of 10MB.";
                                }
                            } else {
                                $status->tute[] = "There was an error uploading the file '$tute_name'.";
                            }
                        }
                    }
                }

                // =========================================================
                // Overlap checks — generalized to whatever scope was resolved,
                // instead of always being level/subject-specific.
                //
                // LIMITATION: this only catches an EXACT match on the resolved
                // scope columns (e.g. two rows both wildcarded at the same
                // intake). It does NOT catch cross-level containment — e.g. a
                // new "entire programme" row won't be flagged against an
                // existing subject-specific row nested inside that programme.
                // Doing that properly needs the full subjects -> levels ->
                // intakes -> programmes -> faculties -> branches join chain,
                // which wasn't available when this was written — verify your
                // FK column names and extend if cross-level conflicts matter
                // to you.
                // =========================================================
                if ($lecturerUserIdForConflictCheck !== null && !empty($lecture_date) && !empty($start_time) && !empty($end_time)) {
                    $isAvailableLecturer = Database::search("SELECT COUNT(*) as 'count' FROM `time_table`
                                                                    LEFT JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                                                    WHERE (`lecturers`.`users_user_id` = '" . Database::escape($lecturerUserIdForConflictCheck) . "' OR `time_table`.`broad_lecturer_user_id` = '" . Database::escape($lecturerUserIdForConflictCheck) . "')
                                                                    AND `time_table`.`date` = '" . Database::escape($lecture_date) . "'
                                                                    AND ((`time_table`.`start_time` BETWEEN '" . Database::escape($start_time) . "' AND '" . Database::escape($end_time) . "')
                                                                    OR (`time_table`.`end_time` BETWEEN '" . Database::escape($start_time) . "' AND '" . Database::escape($end_time) . "'))");

                    $row = $isAvailableLecturer->fetch_assoc();
                    if ($row['count'] > 0) {
                        $status->lecturer = 'The selected time is unavailable for this lecturer.';
                    }
                }

                if ($one_time && !empty($resolvedScope) && !empty($lecture_date) && !empty($start_time) && !empty($end_time)) {
                    $conflictConditions = [];
                    foreach ($resolvedScope as $column => $value) {
                        $conflictConditions[] = "`$column` = '" . Database::escape($value) . "'";
                    }
                    $conflictWhere = implode(" AND ", $conflictConditions);

                    $isAlreadyScheduled = Database::search("SELECT COUNT(*) as 'count' FROM `time_table`
                                                                    WHERE $conflictWhere
                                                                    AND `date` = '" . Database::escape($lecture_date) . "'
                                                                    AND ((`start_time` BETWEEN '" . Database::escape($start_time) . "' AND '" . Database::escape($end_time) . "')
                                                                    OR (`end_time` BETWEEN '" . Database::escape($start_time) . "' AND '" . Database::escape($end_time) . "'))");
                    $row = $isAlreadyScheduled->fetch_assoc();
                    if ($row['count'] > 0) {
                        $status->date = 'The selected time has already been scheduled for a lecture in this scope.';
                    }
                }

                $requiredProperties = [
                    'branch',
                    'faculty',
                    'programme',
                    'intake',
                    'level',
                    'subject',
                    'lecturer',
                    'lecture_date',
                    'start_time',
                    'end_time',
                    'link',
                    'tute',
                    'title',
                    'physical',
                    'online',
                    'date',
                    'start_date',
                    'end_date',
                    'days'
                ];
                $allGood = true;
                foreach ($requiredProperties as $property) {
                    if (property_exists($status, $property)) {
                        $allGood = false;
                        break;
                    }
                }

                if ($allGood) {
                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $addTime = $dateTime->format('Y-m-d H:i:s');

                    $start = new DateTime($start_time);
                    $end = new DateTime($end_time);

                    $interval = $start->diff($end);

                    $duration = ($interval->h * 60) + $interval->i;

                    // Build the scope + lecturer columns dynamically from whatever
                    // was resolved above — replaces the old hardcoded subject-only
                    // insert.
                    $insertColumns = [];
                    $insertValues = [];

                    foreach ($resolvedScope as $column => $value) {
                        $insertColumns[] = "`$column`";
                        $insertValues[] = "'" . Database::escape($value) . "'";
                    }

                    if ($subjectResolved) {
                        $insertColumns[] = "`lecturers_lecturers_id`";
                        $insertValues[] = "'" . Database::escape($lecturer_id) . "'";
                    } else {
                        $insertColumns[] = "`broad_lecturer_user_id`";
                        $insertValues[] = "'" . Database::escape($broad_lecturer_user_id) . "'";
                    }

                    if ($physical) {
                        $insertColumns[] = "`lecture_type_lecture_type_id`";
                        $insertColumns[] = "`lecture`";
                        $insertValues[] = "'1'";
                        $insertValues[] = "''";
                    }
                    if ($online) {
                        $insertColumns[] = "`lecture_type_lecture_type_id`";
                        $insertColumns[] = "`lecture`";
                        $insertValues[] = "'2'";
                        $insertValues[] = "'" . Database::escape($link) . "'";
                    }
                    if ($hybrid) {
                        $insertColumns[] = "`lecture_type_lecture_type_id`";
                        $insertColumns[] = "`lecture`";
                        $insertValues[] = "'4'";
                        $insertValues[] = "'" . Database::escape($link) . "'";
                    }

                    if ($one_time) {
                        $baseColumns = "`date`,`start_time`,`end_time`,`duration`,`date_added`,`users_user_id`,`time_table_status_time_table_status_id`,`lecture_title`";
                        $baseValues = "'" . Database::escape($lecture_date) . "','" . Database::escape($start_time) . "','" . Database::escape($end_time) . "','" . $duration . "','" . $addTime . "','" . $sessionUserObject["user_id"] . "','1','" . Database::escape($title) . "'";

                        $time_table_insert_columns = $baseColumns . "," . implode(",", $insertColumns);
                        $time_table_insert_values = $baseValues . "," . implode(",", $insertValues);

                        try {
                            Database::iud("INSERT INTO `time_table` (" . $time_table_insert_columns . ") VALUES (" . $time_table_insert_values . ")");

                            $time_table_id = Database::$connection->insert_id;

                            if (isset($_FILES["tutes"])) {
                                for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {
                                    $originalFileName = $_FILES["tutes"]["name"][$i];
                                    $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`,`attachments_order`";
                                    $attachment_insert_values = "'" . $tutes[$i] . "','" . Database::escape($originalFileName) . "','" . $time_table_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";

                                    Database::iud("INSERT INTO `attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");

                                    $tute_path = "../assets/uploads/lectures-attachments/" . $tutes[$i];
                                    move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $tute_path);
                                }
                            }

                            $status->done = "Lecture has been scheduled successfully";
                        } catch (Exception $e) {
                            echo $e->getMessage();
                        }
                    }

                    if ($recurring) {
                        $poyaDays = [];
                        $specialHolidays = [];

                        $holidays = getYearHolidays();

                        foreach ($holidays as $holiday) {
                            if (!isset($holiday['categories'])) {
                                continue;
                            }
                            $categories = $holiday['categories'];

                            if (!is_array($categories)) {
                                $categories = explode(',', $categories);
                            }
                            $categories = array_map('strtolower', $categories);

                            if (in_array('poya', $categories)) {
                                $poyaDays[] = $holiday['start'];
                            } else {
                                $specialHolidays[] = $holiday['start'];
                            }
                        }

                        $lectureDays = generateLectureDates($start_date, $end_date, $days, $skip_poya_days, $skip_holidays, $poyaDays, $specialHolidays);
                        if (count($lectureDays) == 0) {
                            $status->error = "No Lecture Dates Found";
                        } else {

                            $existingCountQuery = "SELECT COUNT(*) as count FROM `time_table` WHERE 1=1";
                            foreach ($resolvedScope as $column => $value) {
                                $existingCountQuery .= " AND `$column` = '" . Database::escape($value) . "'";
                            }
                            $addedLecturesCount = Database::search($existingCountQuery);
                            $addedLecturesCountData = $addedLecturesCount->fetch_assoc();
                            $baseCount = $addedLecturesCountData['count'];

                            // Falls back to $deepestScopeName (branch/faculty/programme/
                            // intake/subject name, whichever was resolved deepest) instead
                            // of always assuming a subject was picked.
                            $titleBase = $deepestScopeName ?: 'Lecture';

                            for ($i = 0; $i < count($lectureDays); $i++) {
                                $lecture_date_i = $lectureDays[$i];
                                $lecture_title = Database::escape($titleBase . " - Lecture Day " . ($baseCount + $i + 1));

                                $baseColumns = "`date`,`start_time`,`end_time`,`duration`,`date_added`,`users_user_id`,`time_table_status_time_table_status_id`,`lecture_title`";
                                $baseValues = "'" . Database::escape($lecture_date_i) . "','" . Database::escape($start_time) . "','" . Database::escape($end_time) . "','" . $duration . "','" . $addTime . "','" . $sessionUserObject["user_id"] . "','1','" . $lecture_title . "'";

                                $time_table_insert_columns = $baseColumns . "," . implode(",", $insertColumns);
                                $time_table_insert_values = $baseValues . "," . implode(",", $insertValues);

                                Database::iud("INSERT INTO `time_table` (" . $time_table_insert_columns . ") VALUES (" . $time_table_insert_values . ")");
                            }
                            $status->done = "Lecture has been scheduled successfully";
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


function checkIfFutureDateTime($lecture_date, $start_time)
{
    if ($lecture_date && $start_time) {
        $startDateTime = new DateTime("$lecture_date $start_time");
        $now = new DateTime();
        $isToday = $startDateTime->format('Y-m-d') === $now->format('Y-m-d');
        if (($isToday && $startDateTime > $now) || $startDateTime > $now) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function getYearHolidays()
{
    $year = date('Y');
    $jsonUrl = "https://raw.githubusercontent.com/Dilshan-H/srilanka-holidays/main/json/{$year}.json";

    $jsonData = file_get_contents($jsonUrl);
    if ($jsonData === false) {
        return [];
    }

    $decoded = json_decode($jsonData, true);

    if (isset($decoded['holidays']) && is_array($decoded['holidays'])) {
        return $decoded['holidays'];
    }

    if (is_array($decoded)) {
        return $decoded;
    }

    return [];
}


function generateLectureDates(
    $startDate,
    $endDate,
    $selectedDays,
    $skipPoya,
    $skipHolidays,
    $poyaDays,
    $holidays
) {
    $dates = [];

    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');

    $period = new DatePeriod($start, new DateInterval('P1D'), $end);
    if (!is_array($selectedDays)) {
        $selectedDays = array_map('intval', explode(',', $selectedDays));
    }

    foreach ($period as $date) {
        $currentDate = $date->format('Y-m-d');
        $dayOfWeek = (int)$date->format('N');

        if (!in_array($dayOfWeek, $selectedDays)) {
            continue;
        }

        if ($skipPoya && in_array($currentDate, $poyaDays)) {
            continue;
        }

        if ($skipHolidays && in_array($currentDate, $holidays)) {
            continue;
        }

        $dates[] = $currentDate;
    }

    return $dates;
}
