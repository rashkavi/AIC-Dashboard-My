<?php

/**
 * Multi-branch lecture scheduling: takes a set of subject_ids (checked in the course
 * structure table) plus one lecturer, chosen by user_id, and creates one time_table
 * entry per subject at the same date/time — used for combined sessions (e.g. one
 * online lecture serving the same subject across several branches).
 *
 * IMPORTANT: the lecturer is selected by user_id (not lecturers_id), because the same
 * person has a separate `lecturers` row per subject they teach. For every subject_id in
 * the batch we look up THAT subject's own lecturers_id for this user_id below — that's
 * the row time_table.lecturers_lecturers_id actually needs to reference.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 2))) {
                $status->error = "You do not have permission to schedule a lecture";
            } else {

                $subjectIds = isset($_POST['subject_ids']) && is_array($_POST['subject_ids']) ? $_POST['subject_ids'] : [];
                $subjectIds = array_values(array_unique(array_filter(array_map('intval', $subjectIds), function ($id) {
                    return $id > 0;
                })));

                $lecturer_user_id = isset($_POST['lecturer_user_id']) ? (int)trim($_POST['lecturer_user_id']) : 0;
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

                if (count($subjectIds) === 0) {
                    $status->subjects = 'Please select at least one subject from the table';
                }
                if ($lecturer_user_id == 0) {
                    $status->lecturer = 'Please select a lecturer';
                }

                // Resolve each subject's own lecturers_id for this user_id, and confirm
                // this user really is assigned to every one of the checked subjects
                // (re-validated server-side — the picker only reflects what the client
                // last fetched, which could be stale by the time Save is pressed).
                $subjectLecturerMap = []; // subject_id => ['lecturers_id' => ..., 'subject' => ..., 'levels_level_id' => ...]
                if ($lecturer_user_id != 0 && count($subjectIds) > 0) {
                    $idsList = implode(',', $subjectIds);
                    $lecturerRows = Database::search("SELECT
                            `lecturers`.`lecturers_id`,
                            `lecturers`.`subjects_subject_id`,
                            `subjects`.`subject`,
                            `subjects`.`levels_level_id`
                        FROM `lecturers`
                        INNER JOIN `subjects` ON `lecturers`.`subjects_subject_id` = `subjects`.`subject_id`
                        WHERE `lecturers`.`users_user_id` = '" . $lecturer_user_id . "'
                          AND `lecturers`.`subjects_subject_id` IN (" . $idsList . ")
                          AND `lecturers`.`status_status_id` = 1");

                    while ($row = $lecturerRows->fetch_assoc()) {
                        $subjectLecturerMap[(int)$row['subjects_subject_id']] = $row;
                    }

                    $missingSubjects = array_diff($subjectIds, array_keys($subjectLecturerMap));
                    if (count($missingSubjects) > 0) {
                        $status->lecturer = 'The selected lecturer is not assigned to every selected subject. Please re-check your subject selection.';
                    }
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
                                    $tutes[$key] = ['generated' => $tute, 'original' => $tute_name, 'tmp_name' => $_FILES["tutes"]["tmp_name"][$key]];
                                } else {
                                    $status->tute[] = "File '$tute_name' exceeds the maximum allowed size of 10MB.";
                                }
                            } else {
                                $status->tute[] = "There was an error uploading the file '$tute_name'.";
                            }
                        }
                    }
                }

                if ($recurring) {
                    if (empty($start_date)) {
                        $status->start_date = 'Please select a recurring lecture start date';
                    } else if (!CheckDateTime::isFutureDate($start_date)) {
                        $status->start_date = 'Please select a future date';
                    }

                    if (empty($end_date)) {
                        $status->end_date = 'Please select a recurring lecture end date';
                    } else if (!CheckDateTime::isFutureDate($end_date)) {
                        $status->end_date = 'Please select a future date';
                    }

                    if (empty($days)) {
                        $status->days = 'Please select at least one day';
                    }
                }

                $requiredProperties = [
                    'subjects',
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

                    // Move each uploaded file to disk ONCE — every subject's time_table
                    // row gets its own `attachments` record pointing at the same file,
                    // rather than re-uploading per subject.
                    foreach ($tutes as $key => $tute) {
                        $tute_path = "../assets/uploads/lectures-attachments/" . $tute['generated'];
                        move_uploaded_file($tute['tmp_name'], $tute_path);
                    }

                    $conflicts = [];
                    $insertedCount = 0;

                    foreach ($subjectIds as $subjectId) {
                        $lecturerInfo = $subjectLecturerMap[$subjectId];
                        $lecturers_id = $lecturerInfo['lecturers_id'];
                        $subjectName = $lecturerInfo['subject'];
                        $levelId = $lecturerInfo['levels_level_id'];

                        if ($one_time) {

                            $lecture_title = ($title !== '' ? $title . ' - ' : '') . $subjectName;

                            $time_table_insert_columns = "`date`,`start_time`,`end_time`,`duration`,`subjects_subject_id`,`date_added`,`lecturers_lecturers_id`,`users_user_id`,`time_table_status_time_table_status_id`,`lecture_title`";
                            $time_table_insert_values = "'" . $lecture_date . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $subjectId . "','" . $addTime . "','" . $lecturers_id . "','" . $sessionUserObject["user_id"] . "','1','" . Database::escape($lecture_title) . "'";

                            if ($physical) {
                                $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                                $time_table_insert_values .= ",'1',''";
                            }
                            if ($online) {
                                $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                                $time_table_insert_values .= ",'2','" . Database::escape($link) . "'";
                            }
                            if ($hybrid) {
                                $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                                $time_table_insert_values .= ",'4','" . Database::escape($link) . "'";
                            }

                            Database::iud("INSERT INTO `time_table` (" . $time_table_insert_columns . ") VALUES (" . $time_table_insert_values . ")");
                            $time_table_id = Database::$connection->insert_id;
                            $insertedCount++;

                            foreach ($tutes as $tute) {
                                $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`,`attachments_order`";
                                $attachment_insert_values = "'" . $tute['generated'] . "','" . Database::escape($tute['original']) . "','" . $time_table_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";
                                Database::iud("INSERT INTO `attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");
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
                                $conflicts[] = $subjectName . ': no lecture dates found in the selected range';
                                continue;
                            }

                            $addedSubjectLectures = Database::search("SELECT COUNT(*) as count FROM `time_table`
                                WHERE `subjects_subject_id` = '" . $subjectId . "' AND `lecture_type_lecture_type_id` != 3");
                            $addedSubjectLecturesData = $addedSubjectLectures->fetch_assoc();
                            $existingCount = (int)$addedSubjectLecturesData['count'];

                            for ($i = 0; $i < count($lectureDays); $i++) {
                                $day_date = $lectureDays[$i];
                                $lecture_title = $subjectName . " - Lecture Day " . ($existingCount + $i + 1);

                                $time_table_insert_columns = "`date`,`start_time`,`end_time`,`duration`,`subjects_subject_id`,`date_added`,`lecturers_lecturers_id`,`users_user_id`,`time_table_status_time_table_status_id`,`lecture_title`";
                                $time_table_insert_values = "'" . $day_date . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $subjectId . "','" . $addTime . "','" . $lecturers_id . "','" . $sessionUserObject["user_id"] . "','1','" . Database::escape($lecture_title) . "'";

                                if ($physical) {
                                    $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                                    $time_table_insert_values .= ",'1',''";
                                }
                                if ($online) {
                                    $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                                    $time_table_insert_values .= ",'2','" . Database::escape($link) . "'";
                                }
                                if ($hybrid) {
                                    $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                                    $time_table_insert_values .= ",'4','" . Database::escape($link) . "'";
                                }

                                Database::iud("INSERT INTO `time_table` (" . $time_table_insert_columns . ") VALUES (" . $time_table_insert_values . ")");
                                $time_table_id = Database::$connection->insert_id;
                                $insertedCount++;

                                foreach ($tutes as $tute) {
                                    $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`,`attachments_order`";
                                    $attachment_insert_values = "'" . $tute['generated'] . "','" . Database::escape($tute['original']) . "','" . $time_table_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";
                                    Database::iud("INSERT INTO `attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");
                                }
                            }
                        }
                    }

                    if ($insertedCount === 0) {
                        $status->error = "No lectures were scheduled — " . implode('; ', $conflicts);
                    } elseif (count($conflicts) > 0) {
                        $status->done = $insertedCount . " lecture(s) scheduled successfully. Skipped due to conflicts: " . implode('; ', $conflicts);
                    } else {
                        $status->done = $insertedCount . " lecture(s) scheduled successfully across all selected subjects";
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
