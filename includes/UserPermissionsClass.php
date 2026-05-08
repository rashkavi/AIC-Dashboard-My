<?php

class UserPermissions
{
    private static $userPermissions;

    public static function hasPermission($type, $permissionId, $permission_type_related_id = null): bool
    {
        foreach (self::$userPermissions as $perm) {
            if (
                $perm->permission_type === $type &&
                $perm->permission_id == $permissionId &&
                (
                    $permission_type_related_id === null ||
                    $perm->permission_type_related_id == $permission_type_related_id
                )
            ) {
                return true;
            }
        }
        return false;
    }

    public static function validPermisisonType($type, $permission_type_related_id = null): bool
    {
        foreach (self::$userPermissions as $perm) {
            if (
                $perm->permission_type === $type &&
                (
                    $permission_type_related_id === null ||
                    $perm->permission_type_related_id == $permission_type_related_id
                )
            ) {
                return true;
            }
        }
        return false;
    }

    public static function setUserPermissions($userPermissions)
    {
        self::$userPermissions = $userPermissions;
    }
}
