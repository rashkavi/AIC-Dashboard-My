<?php
class CheckDateTime
{
    private static DateTimeZone $tz;

    // Initialize timezone once
    public static function init()
    {
        self::$tz = new DateTimeZone('Asia/Colombo');
    }

    public static function checkIfFutureDateTime($date, $time)
    {
        if ($date && $time) {
            $startDateTime = new DateTime("$date $time", self::$tz);
            $now = new DateTime('now', self::$tz);
            $isToday = $startDateTime->format('Y-m-d') === $now->format('Y-m-d');
            return ($isToday && $startDateTime > $now) || $startDateTime > $now;
        }
        return false;
    }

    public static function checkIfStartTimeBeforeEndTime($exam_date, $startTime, $endTime)
    {
        if ($exam_date && $startTime && $endTime) {
            $startDateTime = new DateTime("$exam_date $startTime", self::$tz);
            $endDateTime = new DateTime("$exam_date $endTime", self::$tz);
            return $startDateTime < $endDateTime;
        }
        return false;
    }

    public static function checkTimeStatus($date, $start_time, $endDate, $end_time)
    {
        $start_datetime = new DateTime("$date $start_time", self::$tz);
        $end_datetime = new DateTime("$endDate $end_time", self::$tz);
        $current_datetime = new DateTime('now', self::$tz);

        if ($current_datetime < $start_datetime) {
            return "Upcoming";
        } elseif ($current_datetime >= $start_datetime && $current_datetime <= $end_datetime) {
            return "Ongoing";
        } else {
            return "Completed";
        }
    }

    public static function formatTimeTo12Hour($time)
    {
        return date('h:i A', strtotime($time));
    }

    public static function validateDuration($startDate, $startTime, $endDate, $endTime, $duration)
    {
        try {
            $startDateTime = new DateTime("$startDate $startTime", self::$tz);
            $endDateTime = new DateTime("$endDate $endTime", self::$tz);

            if ($endDateTime <= $startDateTime) {
                return false;
            }

            $interval = $startDateTime->diff($endDateTime);
            $gapMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

            return $gapMinutes >= $duration;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function isFutureDate($date)
    {
        if (!$date) {
            return false;
        }

        $selectedDate = new DateTime($date, self::$tz);
        $today = new DateTime('today', self::$tz);

        return $selectedDate > $today;
    }
}

// Call once on app init
CheckDateTime::init();
