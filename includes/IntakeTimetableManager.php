<?php
// includes/classes/IntakeTimetableManager.php
//
// Intake Summary Timetable.
// A timetable belongs to one intake and is made of sections (optional heading)
// and rows (module, delivery mode, day, commence date, time, lecturer).
// Only ONE timetable per intake is ACTIVE: creating a new one (or activating
// an old one) marks the current active one INACTIVE.

class IntakeTimetableManager
{
    const STATUS_ACTIVE   = 'ACTIVE';
    const STATUS_INACTIVE = 'INACTIVE';

    const DELIVERY_MODES = ['ONLINE', 'PHYSICAL', 'HYBRID'];

    const MAX_SECTIONS = 20;
    const MAX_ROWS     = 150;

    /* ════════════════════════════════════════════════════════════════════
       Helpers
    ════════════════════════════════════════════════════════════════════ */

    public static function now(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
    }

    // Quoted SQL value, or NULL
    private static function q($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        return "'" . Database::escape((string) $value) . "'";
    }

    private static function connection()
    {
        if (empty(Database::$connection)) {
            Database::search("SELECT 1");   // opens the shared connection
        }
        return Database::$connection;
    }

    private static function normTime($time): ?string
    {
        $time = trim((string) $time);
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $time, $m)) {
            return null;
        }
        return $m[1] . ':' . $m[2] . ':00';
    }

    private static function validDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    /* ════════════════════════════════════════════════════════════════════
       Permissions
    ════════════════════════════════════════════════════════════════════ */

    // Call UserPermissions::setUserPermissions() before these.
    public static function canView($role, int $intakeId): bool
    {
        return SessionUser::ifUserIsPro($role)
            || SessionUser::ifUserIsAcademic($role)
            || UserPermissions::validPermisisonType('intake', $intakeId)
            || UserPermissions::validPermisisonType('intake_students', $intakeId);
    }

    public static function canManage($role, int $intakeId): bool
    {
        return SessionUser::ifUserIsPro($role)
            || SessionUser::ifUserIsAcademic($role)
            || UserPermissions::hasPermission('intake', 2, $intakeId)
            || UserPermissions::hasPermission('intake', 10, $intakeId);
    }

    /* ════════════════════════════════════════════════════════════════════
       Reads
    ════════════════════════════════════════════════════════════════════ */

    public static function getIntake(int $intakeId): ?array
    {
        $r = Database::search("
            SELECT i.intake_id, i.intake,
                   p.programme_id, p.programme,
                   f.faculty_id, f.faculty
            FROM intakes i
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
            INNER JOIN faculties  f ON p.faculties_faculty_id    = f.faculty_id
            WHERE i.intake_id = '" . $intakeId . "'
            LIMIT 1
        ");
        return ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
    }

    public static function getHeader(int $timetableId): ?array
    {
        $r = Database::search("
            SELECT intake_summary_timetable_id AS id,
                   intakes_intake_id           AS intake_id,
                   title, note, status, date_added, date_updated,
                   last_notified_at, last_notified_count
            FROM intake_summary_timetables
            WHERE intake_summary_timetable_id = '" . $timetableId . "'
            LIMIT 1
        ");
        return ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
    }

    public static function listTimetables(int $intakeId): array
    {
        $r = Database::search("
            SELECT t.intake_summary_timetable_id AS id,
                   t.title, t.note, t.status, t.date_added, t.date_updated,
                   t.last_notified_at, t.last_notified_count,
                   CONCAT(u.user_fname, ' ', u.user_lname) AS created_by,
                   (SELECT COUNT(*)
                      FROM intake_summary_timetable_rows r
                      INNER JOIN intake_summary_timetable_sections s
                              ON r.intake_summary_timetable_sections_id = s.intake_summary_timetable_section_id
                     WHERE s.intake_summary_timetables_id = t.intake_summary_timetable_id) AS row_count
            FROM intake_summary_timetables t
            INNER JOIN users u ON t.users_user_id = u.user_id
            WHERE t.intakes_intake_id = '" . $intakeId . "'
            ORDER BY (t.status = 'ACTIVE') DESC, t.intake_summary_timetable_id DESC
        ");

        $list = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $row['id']                  = (int) $row['id'];
                $row['row_count']           = (int) $row['row_count'];
                $row['last_notified_count'] = (int) $row['last_notified_count'];
                $list[] = $row;
            }
        }
        return $list;
    }

    // Full timetable: header + sections + rows
    public static function getTimetable(int $timetableId): ?array
    {
        $timetable = self::getHeader($timetableId);
        if (!$timetable) {
            return null;
        }
        $timetable['id']        = (int) $timetable['id'];
        $timetable['intake_id'] = (int) $timetable['intake_id'];
        $timetable['sections']  = [];

        $map = [];   // section_id => index in sections[]
        $s = Database::search("
            SELECT intake_summary_timetable_section_id AS id, heading
            FROM intake_summary_timetable_sections
            WHERE intake_summary_timetables_id = '" . $timetableId . "'
            ORDER BY sort_order, intake_summary_timetable_section_id
        ");
        if ($s) {
            while ($sec = $s->fetch_assoc()) {
                $map[(int) $sec['id']] = count($timetable['sections']);
                $timetable['sections'][] = [
                    'heading' => (string) ($sec['heading'] ?? ''),
                    'rows'    => [],
                ];
            }
        }

        if ($map) {
            $r = Database::search("
                SELECT intake_summary_timetable_sections_id AS section_id,
                       subjects_subject_id, module_label, delivery_mode, delivery_note,
                       schedule_day, commence_date, start_time, end_time,
                       lecturer_user_id, lecturer_details
                FROM intake_summary_timetable_rows
                WHERE intake_summary_timetable_sections_id IN (" . implode(',', array_keys($map)) . ")
                ORDER BY sort_order, intake_summary_timetable_row_id
            ");
            if ($r) {
                while ($row = $r->fetch_assoc()) {
                    $idx = $map[(int) $row['section_id']];
                    $timetable['sections'][$idx]['rows'][] = [
                        'subject_id'       => (int) ($row['subjects_subject_id'] ?? 0),
                        'module_label'     => $row['module_label'],
                        'delivery_mode'    => $row['delivery_mode'],
                        'delivery_note'    => (string) ($row['delivery_note'] ?? ''),
                        'schedule_day'     => $row['schedule_day'],
                        'commence_date'    => (string) ($row['commence_date'] ?? ''),
                        'start_time'       => substr($row['start_time'], 0, 5),
                        'end_time'         => substr($row['end_time'], 0, 5),
                        'lecturer_user_id' => (int) ($row['lecturer_user_id'] ?? 0),
                        'lecturer_details' => (string) ($row['lecturer_details'] ?? ''),
                    ];
                }
            }
        }

        return $timetable;
    }

    // Subjects of the intake + their active lecturers (for the builder dropdowns)
    public static function getOptions(int $intakeId): array
    {
        $subjects = [];
        $r = Database::search("
            SELECT s.subject_id, s.subject, l.level
            FROM subjects s
            INNER JOIN levels l ON s.levels_level_id = l.level_id
            WHERE l.intakes_intake_id = '" . $intakeId . "'
            ORDER BY l.level_id, s.subject_id
        ");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $subjects[] = [
                    'id'    => (int) $row['subject_id'],
                    'name'  => $row['subject'],
                    'level' => $row['level'],
                ];
            }
        }

        $bySubject = [];
        $all       = [];
        if ($subjects) {
            $ids = implode(',', array_column($subjects, 'id'));
            $r = Database::search("
                SELECT le.subjects_subject_id AS subject_id,
                       u.user_id, t.title, u.user_fname, u.user_lname, u.user_email
                FROM lecturers le
                INNER JOIN users u ON le.users_user_id = u.user_id
                INNER JOIN title t ON u.title_title_id = t.title_id
                WHERE le.subjects_subject_id IN (" . $ids . ")
                  AND le.status_status_id = '1'
                ORDER BY u.user_fname, u.user_lname
            ");
            if ($r) {
                while ($row = $r->fetch_assoc()) {
                    $lecturer = [
                        'id'    => (int) $row['user_id'],
                        'name'  => $row['title'] . '. ' . $row['user_fname'] . ' ' . $row['user_lname'],
                        'email' => (string) ($row['user_email'] ?? ''),
                    ];
                    $bySubject[(int) $row['subject_id']][$lecturer['id']] = $lecturer;
                    $all[$lecturer['id']] = $lecturer;
                }
            }
        }

        $lecturersBySubject = new stdClass();
        foreach ($bySubject as $subjectId => $lecturers) {
            $lecturersBySubject->{$subjectId} = array_values($lecturers);
        }

        return [
            'subjects'             => $subjects,
            'lecturers_by_subject' => $lecturersBySubject,
            'lecturers'            => array_values($all),
        ];
    }

    // Active, enrolled students of the intake (same filter as the intake Students list)
    public static function getRecipients(int $intakeId): array
    {
        $r = Database::search("
            SELECT DISTINCT
                ld.email,
                CONCAT(ld.first_name, ' ', ld.last_name) AS student_name
            FROM students_has_intakes shi
            INNER JOIN students      s  ON shi.students_student_id          = s.student_id
            INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
            WHERE shi.intakes_intake_id = '" . $intakeId . "'
              AND shi.status_status_id  = '1'
              AND s.enrolled_status     = 'ENROLLED'
              AND (shi.lead_student_status_lead_student_status_id IS NULL
                   OR shi.lead_student_status_lead_student_status_id = '17')
              AND ld.email IS NOT NULL
              AND ld.email <> ''
        ");

        $recipients = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $recipients[$row['email']] = trim($row['student_name']);
            }
        }
        return $recipients;
    }

    /* ════════════════════════════════════════════════════════════════════
       Validation
    ════════════════════════════════════════════════════════════════════ */

    // Returns [cleanData, errors[]]
    public static function validate(array $p, int $intakeId): array
    {
        $errors = [];

        $title = trim((string) ($p['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Please enter a timetable title.';
        } elseif (mb_strlen($title) > 150) {
            $errors[] = 'Timetable title must be 150 characters or less.';
        }

        $note = trim((string) ($p['note'] ?? ''));
        if (mb_strlen($note) > 2000) {
            $errors[] = 'Note must be 2000 characters or less.';
        }

        $sectionsIn = $p['sections'] ?? [];
        if (!is_array($sectionsIn) || count($sectionsIn) === 0) {
            $errors[] = 'Add at least one section.';
            $sectionsIn = [];
        } elseif (count($sectionsIn) > self::MAX_SECTIONS) {
            $errors[] = 'A timetable can have at most ' . self::MAX_SECTIONS . ' sections.';
        }

        $options    = self::getOptions($intakeId);
        $subjectIds = array_flip(array_column($options['subjects'], 'id'));

        $sections    = [];
        $totalRows   = 0;
        $lecturerIds = [];

        foreach (array_values($sectionsIn) as $si => $sec) {
            $sn = $si + 1;
            if (!is_array($sec)) {
                $errors[] = "Section {$sn}: invalid data.";
                continue;
            }

            $heading = trim((string) ($sec['heading'] ?? ''));
            if (mb_strlen($heading) > 255) {
                $errors[] = "Section {$sn}: heading must be 255 characters or less.";
            }

            $rowsIn = $sec['rows'] ?? [];
            if (!is_array($rowsIn) || count($rowsIn) === 0) {
                $errors[] = "Section {$sn}: add at least one module.";
                continue;
            }

            $rows = [];
            foreach (array_values($rowsIn) as $ri => $row) {
                $at = "Section {$sn}, module " . ($ri + 1);
                if (!is_array($row)) {
                    $errors[] = "{$at}: invalid data.";
                    continue;
                }

                $subjectId = (int) ($row['subject_id'] ?? 0);
                if ($subjectId > 0 && !isset($subjectIds[$subjectId])) {
                    $errors[] = "{$at}: the selected subject does not belong to this intake.";
                }

                $label = trim((string) ($row['module_label'] ?? ''));
                if ($label === '') {
                    $errors[] = "{$at}: enter the module code and name.";
                } elseif (mb_strlen($label) > 255) {
                    $errors[] = "{$at}: module code and name must be 255 characters or less.";
                }

                $mode = strtoupper(trim((string) ($row['delivery_mode'] ?? '')));
                if (!in_array($mode, self::DELIVERY_MODES, true)) {
                    $errors[] = "{$at}: select a delivery mode.";
                }

                $deliveryNote = trim((string) ($row['delivery_note'] ?? ''));
                if (mb_strlen($deliveryNote) > 150) {
                    $errors[] = "{$at}: delivery details must be 150 characters or less.";
                }

                $day = trim((string) ($row['schedule_day'] ?? ''));
                if ($day === '') {
                    $errors[] = "{$at}: enter the day (e.g. Every Saturday).";
                } elseif (mb_strlen($day) > 100) {
                    $errors[] = "{$at}: day must be 100 characters or less.";
                }

                $commence = trim((string) ($row['commence_date'] ?? ''));
                if ($commence !== '' && !self::validDate($commence)) {
                    $errors[] = "{$at}: commence date is not valid.";
                }

                $start = self::normTime($row['start_time'] ?? '');
                $end   = self::normTime($row['end_time'] ?? '');
                if ($start === null) {
                    $errors[] = "{$at}: select a start time.";
                }
                if ($end === null) {
                    $errors[] = "{$at}: select an end time.";
                }
                if ($start !== null && $end !== null && $end <= $start) {
                    $errors[] = "{$at}: end time must be after the start time.";
                }

                $lecturerId = (int) ($row['lecturer_user_id'] ?? 0);
                if ($lecturerId > 0) {
                    $lecturerIds[$lecturerId] = true;
                }

                $details = trim((string) ($row['lecturer_details'] ?? ''));
                if (mb_strlen($details) > 1000) {
                    $errors[] = "{$at}: lecturer details must be 1000 characters or less.";
                }

                $rows[] = [
                    'subject_id'       => $subjectId > 0 ? $subjectId : null,
                    'module_label'     => $label,
                    'delivery_mode'    => $mode,
                    'delivery_note'    => $deliveryNote !== '' ? $deliveryNote : null,
                    'schedule_day'     => $day,
                    'commence_date'    => $commence !== '' ? $commence : null,
                    'start_time'       => $start,
                    'end_time'         => $end,
                    'lecturer_user_id' => $lecturerId > 0 ? $lecturerId : null,
                    'lecturer_details' => $details !== '' ? $details : null,
                ];
            }

            $totalRows += count($rows);
            $sections[] = [
                'heading' => $heading !== '' ? $heading : null,
                'rows'    => $rows,
            ];
        }

        if ($totalRows > self::MAX_ROWS) {
            $errors[] = 'A timetable can have at most ' . self::MAX_ROWS . ' modules.';
        }

        if ($lecturerIds) {
            $ids = implode(',', array_map('intval', array_keys($lecturerIds)));
            $r = Database::search("SELECT user_id FROM users WHERE user_id IN (" . $ids . ")");
            $found = [];
            if ($r) {
                while ($row = $r->fetch_assoc()) {
                    $found[(int) $row['user_id']] = true;
                }
            }
            if (count($found) !== count($lecturerIds)) {
                $errors[] = 'One or more selected lecturers could not be found.';
            }
        }

        return [
            [
                'title'    => $title,
                'note'     => $note !== '' ? $note : null,
                'sections' => $sections,
            ],
            $errors,
        ];
    }

    /* ════════════════════════════════════════════════════════════════════
       Writes
    ════════════════════════════════════════════════════════════════════ */

    // Create (timetable_id = 0) or update. Returns ['id', 'deactivated'] or ['errors' => []].
    public static function save(array $p, int $intakeId, int $userId): array
    {
        [$clean, $errors] = self::validate($p, $intakeId);
        if ($errors) {
            return ['errors' => $errors];
        }

        $timetableId = (int) ($p['timetable_id'] ?? 0);
        if ($timetableId > 0) {
            $existing = self::getHeader($timetableId);
            if (!$existing || (int) $existing['intake_id'] !== $intakeId) {
                return ['errors' => ['Timetable not found for this intake.']];
            }
        }

        $now  = self::now();
        $conn = self::connection();
        $deactivated = [];

        $conn->begin_transaction();
        try {
            if ($timetableId === 0) {
                // New timetable → the current active one becomes inactive
                $prev = Database::search("
                    SELECT intake_summary_timetable_id AS id
                    FROM intake_summary_timetables
                    WHERE intakes_intake_id = '" . $intakeId . "'
                      AND status = 'ACTIVE'
                ");
                if ($prev) {
                    while ($row = $prev->fetch_assoc()) {
                        $deactivated[] = (int) $row['id'];
                    }
                }
                if ($deactivated) {
                    Database::iud("
                        UPDATE intake_summary_timetables
                        SET status = 'INACTIVE', date_updated = '" . $now . "'
                        WHERE intake_summary_timetable_id IN (" . implode(',', $deactivated) . ")
                    ");
                }

                Database::iud("
                    INSERT INTO intake_summary_timetables
                        (intakes_intake_id, title, note, status, users_user_id, date_added)
                    VALUES (
                        '" . $intakeId . "',
                        " . self::q($clean['title']) . ",
                        " . self::q($clean['note']) . ",
                        'ACTIVE',
                        '" . $userId . "',
                        '" . $now . "'
                    )
                ");
                $timetableId = (int) Database::$connection->insert_id;

                self::log(
                    $timetableId,
                    $userId,
                    'CREATED',
                    'Timetable created' . ($deactivated ? ' (replaced #' . implode(', #', $deactivated) . ')' : '')
                );
                foreach ($deactivated as $oldId) {
                    self::log($oldId, $userId, 'DEACTIVATED', 'Replaced by timetable #' . $timetableId);
                }
            } else {
                Database::iud("
                    UPDATE intake_summary_timetables
                    SET title = " . self::q($clean['title']) . ",
                        note  = " . self::q($clean['note']) . ",
                        date_updated = '" . $now . "'
                    WHERE intake_summary_timetable_id = '" . $timetableId . "'
                ");
                // Rows are removed by ON DELETE CASCADE
                Database::iud("
                    DELETE FROM intake_summary_timetable_sections
                    WHERE intake_summary_timetables_id = '" . $timetableId . "'
                ");
                self::log($timetableId, $userId, 'UPDATED', 'Timetable updated');
            }

            foreach ($clean['sections'] as $sOrder => $section) {
                Database::iud("
                    INSERT INTO intake_summary_timetable_sections
                        (intake_summary_timetables_id, heading, sort_order)
                    VALUES ('" . $timetableId . "', " . self::q($section['heading']) . ", '" . (int) $sOrder . "')
                ");
                $sectionId = (int) Database::$connection->insert_id;

                foreach ($section['rows'] as $rOrder => $row) {
                    Database::iud("
                        INSERT INTO intake_summary_timetable_rows
                            (intake_summary_timetable_sections_id, subjects_subject_id, module_label,
                             delivery_mode, delivery_note, schedule_day, commence_date,
                             start_time, end_time, lecturer_user_id, lecturer_details, sort_order)
                        VALUES (
                            '" . $sectionId . "',
                            " . self::q($row['subject_id']) . ",
                            " . self::q($row['module_label']) . ",
                            " . self::q($row['delivery_mode']) . ",
                            " . self::q($row['delivery_note']) . ",
                            " . self::q($row['schedule_day']) . ",
                            " . self::q($row['commence_date']) . ",
                            " . self::q($row['start_time']) . ",
                            " . self::q($row['end_time']) . ",
                            " . self::q($row['lecturer_user_id']) . ",
                            " . self::q($row['lecturer_details']) . ",
                            '" . (int) $rOrder . "'
                        )
                    ");
                }
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('IntakeTimetableManager::save failed: ' . $e->getMessage());
            return ['errors' => ['The timetable could not be saved. Please try again.']];
        }

        return ['id' => $timetableId, 'deactivated' => count($deactivated)];
    }

    // ACTIVE → also makes every other timetable of the intake INACTIVE
    public static function setStatus(int $timetableId, string $status, int $userId): array
    {
        $status = strtoupper($status);
        if (!in_array($status, [self::STATUS_ACTIVE, self::STATUS_INACTIVE], true)) {
            return ['errors' => ['Invalid status.']];
        }

        $header = self::getHeader($timetableId);
        if (!$header) {
            return ['errors' => ['Timetable not found.']];
        }
        if ($header['status'] === $status) {
            return ['id' => $timetableId, 'deactivated' => 0];
        }

        $intakeId = (int) $header['intake_id'];
        $now      = self::now();
        $conn     = self::connection();
        $deactivated = [];

        $conn->begin_transaction();
        try {
            if ($status === self::STATUS_ACTIVE) {
                $prev = Database::search("
                    SELECT intake_summary_timetable_id AS id
                    FROM intake_summary_timetables
                    WHERE intakes_intake_id = '" . $intakeId . "'
                      AND status = 'ACTIVE'
                      AND intake_summary_timetable_id <> '" . $timetableId . "'
                ");
                if ($prev) {
                    while ($row = $prev->fetch_assoc()) {
                        $deactivated[] = (int) $row['id'];
                    }
                }
                if ($deactivated) {
                    Database::iud("
                        UPDATE intake_summary_timetables
                        SET status = 'INACTIVE', date_updated = '" . $now . "'
                        WHERE intake_summary_timetable_id IN (" . implode(',', $deactivated) . ")
                    ");
                    foreach ($deactivated as $oldId) {
                        self::log($oldId, $userId, 'DEACTIVATED', 'Replaced by timetable #' . $timetableId);
                    }
                }
            }

            Database::iud("
                UPDATE intake_summary_timetables
                SET status = '" . $status . "', date_updated = '" . $now . "'
                WHERE intake_summary_timetable_id = '" . $timetableId . "'
            ");
            self::log(
                $timetableId,
                $userId,
                $status === self::STATUS_ACTIVE ? 'ACTIVATED' : 'DEACTIVATED',
                $status === self::STATUS_ACTIVE ? 'Set as the active timetable' : 'Marked inactive'
            );

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('IntakeTimetableManager::setStatus failed: ' . $e->getMessage());
            return ['errors' => ['The status could not be changed. Please try again.']];
        }

        return ['id' => $timetableId, 'deactivated' => count($deactivated)];
    }

    public static function markNotified(int $timetableId, int $sent, int $total, int $userId): void
    {
        $now = self::now();
        Database::iud("
            UPDATE intake_summary_timetables
            SET last_notified_at = '" . $now . "', last_notified_count = '" . $sent . "'
            WHERE intake_summary_timetable_id = '" . $timetableId . "'
        ");
        self::log($timetableId, $userId, 'NOTIFIED', "Timetable PDF emailed to {$sent} of {$total} student(s)");
    }

    public static function log(int $timetableId, int $userId, string $action, string $note = ''): void
    {
        Database::iud("
            INSERT INTO intake_summary_timetable_logs
                (intake_summary_timetables_id, users_user_id, action, note, date_time)
            VALUES (
                '" . $timetableId . "',
                '" . $userId . "',
                " . self::q(mb_substr($action, 0, 30)) . ",
                " . self::q($note !== '' ? mb_substr($note, 0, 255) : null) . ",
                '" . self::now() . "'
            )
        ");
    }
}
