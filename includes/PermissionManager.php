<?php
/**
 * Permission Manager Class
 * 
 * Handles default permissions initialization and management
 * Integrates with the database permission system
 */

require_once './includes/DefaultPermissions.php';

class PermissionManager {

    /**
     * Initialize default permissions for a user based on their role
     * 
     * @param int $userId - The user ID
     * @param string $roleName - The role name
     * @param Database $db - Database connection instance
     * @return boolean - True if successful
     */
    public static function initializeDefaultPermissionsForUser($userId, $roleName) {
        
        // Check if role exists in default permissions
        if (!roleExists($roleName)) {
            return false;
        }

        // Get default permissions for the role
        $permissions = getDefaultPermissionsForRole($roleName);

        // Build permission string
        $permissionString = self::buildPermissionString($permissions);

        // Update user with default permissions
        $result = Database::iud(
            "UPDATE `users` SET `user_permissions` = '" . $permissionString . "' WHERE `user_id` = '" . $userId . "'"
        );

        return $result;
    }

    /**
     * Build permission string from array
     * 
     * Format: module1:perm1,perm2|module2:perm1|...
     * 
     * @param array $permissions - Array of modules and permission IDs
     * @return string - Encoded permission string
     */
    public static function buildPermissionString($permissions) {
        $parts = [];

        foreach ($permissions as $module => $permIds) {
            if (is_array($permIds)) {
                $permString = implode(',', $permIds);
                $parts[] = $module . ':' . $permString;
            }
        }

        return implode('|', $parts);
    }

    /**
     * Parse permission string to array
     * 
     * @param string $permissionString - The permission string
     * @return array - Decoded permissions array
     */
    public static function parsePermissionString($permissionString) {
        $permissions = [];

        if (empty($permissionString)) {
            return $permissions;
        }

        $modules = explode('|', $permissionString);

        foreach ($modules as $module) {
            $parts = explode(':', $module);
            if (count($parts) === 2) {
                $moduleName = $parts[0];
                $permIds = explode(',', $parts[1]);
                $permissions[$moduleName] = array_map('intval', $permIds);
            }
        }

        return $permissions;
    }

    /**
     * Add a permission to user
     * 
     * @param int $userId - The user ID
     * @param string $module - The module name
     * @param int $permissionId - The permission ID
     * @return boolean - True if successful
     */
    public static function addPermissionToUser($userId, $module, $permissionId) {
        
        // Get current permissions
        $userQuery = Database::search("SELECT `user_permissions` FROM `users` WHERE `user_id` = '" . $userId . "'");
        
        if ($userQuery->num_rows === 0) {
            return false;
        }

        $userData = $userQuery->fetch_assoc();
        $permissions = self::parsePermissionString($userData['user_permissions']);

        // Add or update permission
        if (!isset($permissions[$module])) {
            $permissions[$module] = [];
        }

        if (!in_array($permissionId, $permissions[$module])) {
            $permissions[$module][] = $permissionId;
        }

        // Update database
        $permissionString = self::buildPermissionString($permissions);
        return Database::iud(
            "UPDATE `users` SET `user_permissions` = '" . $permissionString . "' WHERE `user_id` = '" . $userId . "'"
        );
    }

    /**
     * Remove a permission from user
     * 
     * @param int $userId - The user ID
     * @param string $module - The module name
     * @param int $permissionId - The permission ID
     * @return boolean - True if successful
     */
    public static function removePermissionFromUser($userId, $module, $permissionId) {
        
        // Get current permissions
        $userQuery = Database::search("SELECT `user_permissions` FROM `users` WHERE `user_id` = '" . $userId . "'");
        
        if ($userQuery->num_rows === 0) {
            return false;
        }

        $userData = $userQuery->fetch_assoc();
        $permissions = self::parsePermissionString($userData['user_permissions']);

        // Remove permission
        if (isset($permissions[$module])) {
            $key = array_search($permissionId, $permissions[$module]);
            if ($key !== false) {
                unset($permissions[$module][$key]);
                
                // Remove module if no permissions left
                if (empty($permissions[$module])) {
                    unset($permissions[$module]);
                }
            }
        }

        // Update database
        $permissionString = self::buildPermissionString($permissions);
        return Database::iud(
            "UPDATE `users` SET `user_permissions` = '" . $permissionString . "' WHERE `user_id` = '" . $userId . "'"
        );
    }

    /**
     * Grant all default permissions for a role to user
     * 
     * @param int $userId - The user ID
     * @param string $roleName - The role name
     * @return boolean - True if successful
     */
    public static function grantDefaultRolePermissions($userId, $roleName) {
        return self::initializeDefaultPermissionsForUser($userId, $roleName);
    }

    /**
     * Reset user permissions to role defaults
     * 
     * @param int $userId - The user ID
     * @param string $roleName - The role name
     * @return boolean - True if successful
     */
    public static function resetToDefaultPermissions($userId, $roleName) {
        return self::initializeDefaultPermissionsForUser($userId, $roleName);
    }

    /**
     * Get user's current permissions
     * 
     * @param int $userId - The user ID
     * @return array - Decoded permissions
     */
    public static function getUserPermissions($userId) {
        
        $userQuery = Database::search("SELECT `user_permissions` FROM `users` WHERE `user_id` = '" . $userId . "'");
        
        if ($userQuery->num_rows === 0) {
            return [];
        }

        $userData = $userQuery->fetch_assoc();
        return self::parsePermissionString($userData['user_permissions']);
    }

    /**
     * Check if user has a specific permission
     * 
     * @param int $userId - The user ID
     * @param string $module - The module name
     * @param int $permissionId - The permission ID
     * @return boolean - True if user has permission
     */
    public static function userHasPermission($userId, $module, $permissionId) {
        
        $permissions = self::getUserPermissions($userId);

        if (!isset($permissions[$module])) {
            return false;
        }

        return in_array($permissionId, $permissions[$module]);
    }

    /**
     * Get user role from user ID
     * 
     * @param int $userId - The user ID
     * @return string - The role name
     */
    public static function getUserRole($userId) {
        
        $userQuery = Database::search(
            "SELECT `roles`.`role_name` FROM `users` 
             INNER JOIN `roles` ON `users`.`roles_role_id` = `roles`.`role_id`
             WHERE `users`.`user_id` = '" . $userId . "'"
        );

        if ($userQuery->num_rows === 0) {
            return null;
        }

        $userData = $userQuery->fetch_assoc();
        return $userData['role_name'];
    }

    /**
     * Compare user permissions with default role permissions
     * 
     * @param int $userId - The user ID
     * @param string $roleName - The role name
     * @return array - Differences between current and default
     */
    public static function compareWithDefaults($userId, $roleName) {
        
        $currentPerms = self::getUserPermissions($userId);
        $defaultPerms = getDefaultPermissionsForRole($roleName);

        $differences = [
            'missing' => [],  // Permissions user should have but doesn't
            'extra' => [],    // Permissions user has but shouldn't
        ];

        // Check for missing permissions
        foreach ($defaultPerms as $module => $permIds) {
            if (!isset($currentPerms[$module])) {
                $differences['missing'][$module] = $permIds;
            } else {
                $missing = array_diff($permIds, $currentPerms[$module]);
                if (!empty($missing)) {
                    $differences['missing'][$module] = $missing;
                }
            }
        }

        // Check for extra permissions
        foreach ($currentPerms as $module => $permIds) {
            if (!isset($defaultPerms[$module])) {
                $differences['extra'][$module] = $permIds;
            } else {
                $extra = array_diff($permIds, $defaultPerms[$module]);
                if (!empty($extra)) {
                    $differences['extra'][$module] = $extra;
                }
            }
        }

        return $differences;
    }

    /**
     * Get all available roles
     * 
     * @return array - Array of role names
     */
    public static function getAllRoles() {
        return getAllRoles();
    }

    /**
     * Get all permission modules
     * 
     * @return array - Array of all modules with permissions
     */
    public static function getAllPermissionModules() {
        return getAllPermissionModules();
    }

    /**
     * Get role default permissions summary
     * 
     * @param string $roleName - The role name
     * @return array - Formatted permissions summary
     */
    public static function getRolePermissionsSummary($roleName) {
        
        $permissions = getDefaultPermissionsForRole($roleName);
        $summary = [];

        foreach ($permissions as $module => $permIds) {
            $summary[$module] = [];
            
            foreach ($permIds as $permId) {
                $description = getPermissionDescription($module, $permId);
                $summary[$module][] = [
                    'id' => $permId,
                    'name' => $description
                ];
            }
        }

        return $summary;
    }

    /**
     * Export permissions to JSON format
     * 
     * @param int $userId - The user ID
     * @return string - JSON formatted permissions
     */
    public static function exportPermissionsAsJSON($userId) {
        $permissions = self::getUserPermissions($userId);
        return json_encode($permissions, JSON_PRETTY_PRINT);
    }

    /**
     * Import permissions from JSON
     * 
     * @param int $userId - The user ID
     * @param string $jsonData - JSON formatted permissions
     * @return boolean - True if successful
     */
    public static function importPermissionsFromJSON($userId, $jsonData) {
        
        $permissions = json_decode($jsonData, true);

        if ($permissions === null) {
            return false;
        }

        $permissionString = self::buildPermissionString($permissions);
        return Database::iud(
            "UPDATE `users` SET `user_permissions` = '" . $permissionString . "' WHERE `user_id` = '" . $userId . "'"
        );
    }

}

?>
