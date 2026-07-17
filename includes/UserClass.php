<?php

class SessionUser
{
    private static array $proRoles = ['Developer', 'Super Admin'];
    private static array $userRoles = [];

    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private static function makeSessionKey(string $role): string
    {
        return 'aic_uni_lms_' . strtolower(
            preg_replace('/[^a-zA-Z0-9]+/', '_', trim($role))
        );
    }

    private static function loadRoles(): void
    {
        if (!empty(self::$userRoles)) {
            return;
        }

        $result = Database::search("
            SELECT user_role_id, user_role 
            FROM user_roles
            ORDER BY user_role_id ASC
        ");

        while ($row = $result->fetch_assoc()) {
            $roleName = $row['user_role'];
            $baseKey = self::makeSessionKey($roleName);

            self::$userRoles[$roleName] = [
                "user_role_id" => (int) $row['user_role_id'],
                "role_key" => $baseKey,
                "signed_in_key" => $baseKey . "_signed_in"
            ];
        }
    }

    public static function hasValidSession(): bool
    {
        self::ensureSessionStarted();
        self::loadRoles();

        foreach (self::$userRoles as $sessionKeys) {
            if (
                !empty($_SESSION[$sessionKeys['role_key']]) &&
                !empty($_SESSION[$sessionKeys['signed_in_key']])
            ) {
                return true;
            }
        }

        return false;
    }

    public static function startSession(string $userRole, mixed $data): void
    {
        self::ensureSessionStarted();
        self::loadRoles();

        session_regenerate_id(true);

        if (!isset(self::$userRoles[$userRole])) {
            return;
        }

        $_SESSION[self::$userRoles[$userRole]['signed_in_key']] = true;
        $_SESSION[self::$userRoles[$userRole]['role_key']] = $data;

        $_SESSION['current_user_role_id'] = self::$userRoles[$userRole]['user_role_id'];
        $_SESSION['current_user_role'] = $userRole;
    }

    public static function getCurrentUserRole(): ?string
    {
        self::ensureSessionStarted();

        return $_SESSION['current_user_role'] ?? null;
    }

    public static function getCurrentUserRoleId(): ?int
    {
        self::ensureSessionStarted();

        return $_SESSION['current_user_role_id'] ?? null;
    }

    public static function ifUserIsPro(?string $userRole): bool
    {
        return in_array($userRole, self::$proRoles, true);
    }

    public static function ifUserIsLecturer(?string $userRole): bool
    {
        return $userRole === "Lecturer";
    }

    public static function ifUserIsAccounts(?string $userRole): bool
    {
        return $userRole === "Accounts";
    }

    public static function ifUserIsAcademic(?string $userRole): bool
    {
        return $userRole === "Academic";
    }

    public static function ifUserIsAcdemicAdministration(?string $userRole): bool
    {
        return $userRole === "Head of Admission";
    }

    public static function ifUserIsAcdemicDepartment(?string $userRole): bool
    {
        return $userRole === "Academic Admin";
    }

    public static function ifUserIsCounsiler(?string $userRole): bool
    {
        return in_array(
            $userRole,
            ["Head of Admission", "Counselor"],
            true
        );
    }

    public static function hasPermissionManageInquiry(?string $userRole): bool
    {
        return in_array(
            $userRole,
            ["Head of Admission", "Counselor"],
            true
        );
    }

    public static function getCurrentUserObject(): mixed
    {
        self::ensureSessionStarted();
        self::loadRoles();

        $currentRole = self::getCurrentUserRole();

        if (!$currentRole || !isset(self::$userRoles[$currentRole])) {
            return null;
        }

        $roleKey = self::$userRoles[$currentRole]['role_key'];

        return $_SESSION[$roleKey] ?? null;
    }

    public static function updateSession(string $role, mixed $data): void
    {
        self::ensureSessionStarted();
        self::loadRoles();

        if (
            isset(self::$userRoles[$role]) &&
            !empty($_SESSION[self::$userRoles[$role]['signed_in_key']])
        ) {
            $_SESSION[self::$userRoles[$role]['role_key']] = $data;
        }
    }

    public static function destroySession(): void
    {
        self::ensureSessionStarted();
        self::loadRoles();

        foreach (self::$userRoles as $sessionKeys) {
            unset($_SESSION[$sessionKeys['signed_in_key']]);
            unset($_SESSION[$sessionKeys['role_key']]);
        }

        unset($_SESSION['current_user_role_id']);
        unset($_SESSION['current_user_role']);
    }
}
