<?php
session_start();

class SessionUser
{

    private static $proRoles = ['Developer', 'Super Admin'];

    private static $userRoles = [
        "Developer" => ["role_key" => "times_academy_lms__developer", "signed_in_key" => "times_academy_lms__developer_signed_in"],
        "Super Admin" => ["role_key" => "times_academy_lms__super_admin", "signed_in_key" => "times_academy_lms__super_admin_signed_in"],
        "Admin" => ["role_key" => "times_academy_lms__admin", "signed_in_key" => "times_academy_lms__admin_signed_in"],
        "Director" => ["role_key" => "times_academy_lms__director", "signed_in_key" => "times_academy_lms__director_signed_in"],
        "Manager" => ["role_key" => "times_academy_lms__manager", "signed_in_key" => "times_academy_lms__manager_signed_in"],
        "Assistant Manager" => ["role_key" => "times_academy_lms__assistant_manager", "signed_in_key" => "times_academy_lms__assistant_manager_signed_in"],
        "Academic" => ["role_key" => "times_academy_lms__academic", "signed_in_key" => "times_academy_lms__academic_signed_in"],
        "Lecturer" => ["role_key" => "times_academy_lms__lecturer", "signed_in_key" => "times_academy_lms__lecturer_signed_in"],
        "Accountant" => ["role_key" => "times_academy_lms__accountant", "signed_in_key" => "times_academy_lms__accountant_signed_in"]
    ];

    // Check if a valid session exists
    public static function hasValidSession()
    {
        foreach (self::$userRoles as $role => $sessionKeys) {
            if (!empty($_SESSION[$sessionKeys['role_key']]) && !empty($_SESSION[$sessionKeys['signed_in_key']])) {
                return true;
            }
        }
        return false;
    }

    // Start a new session
    public static function startSession($userRole, $data)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        if (isset(self::$userRoles[$userRole])) {
            $_SESSION[self::$userRoles[$userRole]['signed_in_key']] = true;
            $_SESSION[self::$userRoles[$userRole]['role_key']] = $data;
        }
    }

    // Get current user role
    public static function getCurrentUserRole()
    {
        foreach (self::$userRoles as $role => $sessionKeys) {
            if (!empty($_SESSION[$sessionKeys['signed_in_key']]) && $_SESSION[$sessionKeys['signed_in_key']] === true) {
                return $role;
            }
        }
        return null;
    }

    // Check if user is in a pro role
    public static function ifUserIsPro($userRole)
    {
        return in_array($userRole, self::$proRoles, true);
    }

    public static function ifUserIsLecturer($userRole)
    {
        return $userRole === "Lecturer";
    }


    // Get the full user session object
    public static function getCurrentUserObject()
    {
        foreach (self::$userRoles as $role => $sessionKeys) {
            if (!empty($_SESSION[$sessionKeys['signed_in_key']]) && $_SESSION[$sessionKeys['signed_in_key']] === true) {
                return $_SESSION[$sessionKeys['role_key']];
            }
        }
        return null;
    }

    // Destroy the session
    public static function destroySession()
    {
        foreach (self::$userRoles as $role => $sessionKeys) {
            unset($_SESSION[$sessionKeys['signed_in_key']]);
            unset($_SESSION[$sessionKeys['role_key']]);
        }
    }

    public static function updateSession($role, $data)
    {
        if (array_key_exists($role, self::$userRoles)) {
            if (isset($_SESSION[self::$userRoles[$role]['signed_in_key']]) && $_SESSION[self::$userRoles[$role]['signed_in_key']] === true) {
                $_SESSION[self::$userRoles[$role]['role_key']] = $data;
            }
        }
    }
}
