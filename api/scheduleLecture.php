<?php
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
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 2))) {
                $status->error = "You do not have permission to schedule a lecture";
            } else {

                $faculty = isset($_POST['faculty']) ? trim($_POST['faculty']) : 0;
                $programme_id = isset($_POST['programme_id']) ? trim($_POST['programme_id']) : 0;
                $intake_id = isset($_POST['intake_id']) ? trim($_POST['intake_id']) : 0;
                $level_id = isset($_POST['level_id']) ? trim($_POST['level_id']) : 0;
                $subject_id = isset($_POST['subject_id']) ? trim($_POST['subject_id']) : 0;
                $lecturer_id = isset($_POST['lecturer_id']) ? trim($_POST['lecturer_id']) : 0;
                $lecture_date = isset($_POST['lecture-date']) ? trim($_POST['lecture-date']) : '';
                $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
                $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $physical = isset($_POST['physical']) && $_POST['physical'] === 'true' ? true : false;
                $online = isset($_POST['online']) && $_POST['online'] === 'true' ? true : false;
                $link = isset($_POST['link']) ? trim($_POST['link']) : '';
                $one_time = isset($_POST['one_time']) && $_POST['one_time'] === 'true' ? true : false;
                $recurring = isset($_POST['recurring']) && $_POST['recurring'] === 'true' ? true : false;
                $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
                $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
                $days = isset($_POST['days']) ? trim($_POST['days']) : [];
                $skip_poya_days = isset($_POST['skip_poya_days']) && $_POST['skip_poya_days'] === 'true' ? true : false;
                $skip_holidays = isset($_POST['skip_holidays']) && $_POST['skip_holidays'] === 'true' ? true : false;

                if ($faculty == 0) {
                    $status->faculty = 'Please select a faculty';
                }
                if ($programme_id == 0) {
                    $status->programme = 'Please select a programme';
                }
                if ($intake_id == 0) {
                    $status->intake = 'Please select an intake';
                }
                if ($intake_id != 0 && $sessionUserRole == "academic") {
                    $isValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` 
                                                            INNER JOIN `users_has_intakes` ON `intakes`.`intake_id` = `users_has_intakes`.`intakes_intake_id`
                                                            WHERE `intake_id` = '" . $intake_id . "' AND `users_has_intakes`.`users_user_id` = '" . $sessionUserObject["user_id"] . "'");
                    $row = $isValidIntake->fetch_assoc();
                    if ($row['count'] == 0) {
                        $status->intake = 'You do not have access to this intake shedule lecture';
                    }
                }
                if ($level_id == 0) {
                    $status->level = 'Please select a level';
                }
                if ($subject_id == 0) {
                    $status->subject = 'Please select a subject';
                }
                if ($lecturer_id == 0) {
                    $status->lecturer = 'Please select a lecturer';
                } else {
                    $isValidLecturer = Database::search("SELECT * FROM `lecturers` WHERE `lecturers_id` = '" . $lecturer_id . "' AND `subjects_subject_id` = '" . $subject_id . "'");
                    if ($isValidLecturer->num_rows == 0) {
                        $status->lecturer = 'This lecturer is not assigned to this subject';
                    } else {
                        $lecturerData = $isValidLecturer->fetch_assoc();
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
                if ($online && empty($link)) {
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
                        }else if (strlen($tute_name) > 45) {
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

                if ($one_time) {
                    if ($lecturer_id != 0 && !empty($lecture_date) && !empty($start_time) && !empty($end_time)) {

                        $isAvailableLecturer = Database::search("SELECT COUNT(*) as 'count' FROM `time_table`
                                                                        INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                                                        WHERE `lecturers`.`users_user_id` = '" . $lecturerData['users_user_id'] . "'
                                                                        AND `time_table`.`date` = '" . $lecture_date . "'
                                                                        AND ((`time_table`.`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                        OR (`time_table`.`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))");

                        $row = $isAvailableLecturer->fetch_assoc();
                        if ($row['count'] > 0) {
                            $status->lecturer = 'The selected time is unavailable for this lecturer.';
                        }
                    }

                    if ($level_id != 0 && !empty($lecture_date) && !empty($start_time) && !empty($end_time)) {
                        $isAvailableSelectTimeInIntake = Database::search("SELECT COUNT(*) as 'count' FROM `time_table`
                INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                                  WHERE `subjects_subject_id` IN (SELECT `subjects_subject_id` FROM `subjects` WHERE `levels_level_id` = '" . $level_id . "') 
                                                                                  AND `date` = '" . $lecture_date . "'
                                                                                  AND ((`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                                  OR (`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))
                                                                                                                                                                 ");
                        $row = $isAvailableSelectTimeInIntake->fetch_assoc();
                        if ($row['count'] > 0) {
                            $status->date = 'The selected time has already been scheduled for a lecture in this Intake.';
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
                    'tute',
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

                    if ($one_time) {
                        $time_table_insert_columns = "`date`,`start_time`,`end_time`,`duration`,`subjects_subject_id`,`date_added`,`lecturers_lecturers_id`,`users_user_id`,`time_table_status_time_table_status_id`,`lecture_title`";
                        $time_table_insert_values = "'" . $lecture_date . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $subject_id . "','" . $addTime . "','" . $lecturer_id . "','" . $sessionUserObject["user_id"] . "','1','" . $title . "'";

                        if ($physical) {
                            $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                            $time_table_insert_values .= ",'1',''";
                        }

                        if ($online) {
                            $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                            $time_table_insert_values .= ",'2','" . $link . "'";
                        }

                        try {
                            Database::iud("INSERT INTO `time_table` (" . $time_table_insert_columns . ") VALUES (" . $time_table_insert_values . ")");

                            $time_table_id = Database::$connection->insert_id;

                            if (isset($_FILES["tutes"])) {
                                for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {
                                    $originalFileName = $_FILES["tutes"]["name"][$i];
                                    $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`,`attachments_order`";
                                    $attachment_insert_values = "'" . $tutes[$i] . "','" . $originalFileName . "','" . $time_table_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";

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

                            $addedSubjectLectures = Database::search("SELECT COUNT(*) as count,`subject` FROM `time_table`
                            INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id` 
                            WHERE `subjects_subject_id` = '" . $subject_id . "' AND `lecture_type_lecture_type_id` != 3");

                            $addedSubjectLecturesData = $addedSubjectLectures->fetch_assoc();

                            for ($i = 0; $i < count($lectureDays); $i++) {
                                $lecture_date = $lectureDays[$i];
                                $lecture_title = $addedSubjectLecturesData['subject'] . " - Lecture Day " . ($addedSubjectLecturesData['count'] + $i + 1);
                                $time_table_insert_columns = "`date`,`start_time`,`end_time`,`duration`,`subjects_subject_id`,`date_added`,`lecturers_lecturers_id`,`users_user_id`,`time_table_status_time_table_status_id`,`lecture_title`";
                                $time_table_insert_values = "'" . $lecture_date . "','" . $start_time . "','" . $end_time . "','" . $duration . "','" . $subject_id . "','" . $addTime . "','" . $lecturer_id . "','" . $sessionUserObject["user_id"] . "','1','" . Database::escape($lecture_title) . "'";

                                if ($physical) {
                                    $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                                    $time_table_insert_values .= ",'1',''";
                                }

                                if ($online) {
                                    $time_table_insert_columns .= ",`lecture_type_lecture_type_id`,`lecture`";
                                    $time_table_insert_values .= ",'2','" . $link . "'";
                                }

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

    // ✅ Ensure we always return an array of holidays
    if (isset($decoded['holidays']) && is_array($decoded['holidays'])) {
        return $decoded['holidays'];
    }

    // Some years are already flat arrays
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
