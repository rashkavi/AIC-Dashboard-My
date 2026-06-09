<?php
/**
 * Permission Initialization Script
 * 
 * This script sets up default permissions for all users based on their roles
 * Run this script once after setting up the LMS system
 * 
 * Usage: 
 * 1. Include this file in your setup/admin panel
 * 2. Call initializeAllUserPermissions() to setup all existing users
 * 3. Call initializeUserPermissions($userId) for individual users
 */

require_once './includes/connection.php';
require_once './includes/DefaultPermissions.php';
require_once './includes/PermissionManager.php';

/**
 * Initialize permissions for all users
 * 
 * @return array - Report of initialization
 */
function initializeAllUserPermissions() {
    
    $report = [
        'total_users' => 0,
        'updated_users' => 0,
        'failed_users' => 0,
        'errors' => [],
        'summary' => []
    ];

    // Get all users with their roles
    $usersQuery = Database::search(
        "SELECT `u`.`user_id`, `u`.`user_fname`, `u`.`user_lname`, `r`.`role_name` 
         FROM `users` `u`
         INNER JOIN `roles` `r` ON `u`.`roles_role_id` = `r`.`role_id`
         ORDER BY `r`.`role_name`, `u`.`user_id`"
    );

    $report['total_users'] = $usersQuery->num_rows;

    if ($usersQuery->num_rows > 0) {
        
        while ($userData = $usersQuery->fetch_assoc()) {
            
            $userId = $userData['user_id'];
            $roleName = $userData['role_name'];
            $userName = $userData['user_fname'] . ' ' . $userData['user_lname'];

            try {
                // Initialize default permissions for user
                $result = PermissionManager::initializeDefaultPermissionsForUser($userId, $roleName);

                if ($result) {
                    $report['updated_users']++;
                    
                    // Track by role
                    if (!isset($report['summary'][$roleName])) {
                        $report['summary'][$roleName] = 0;
                    }
                    $report['summary'][$roleName]++;
                } else {
                    $report['failed_users']++;
                    $report['errors'][] = "Failed to update user: $userName (ID: $userId)";
                }
            } catch (Exception $e) {
                $report['failed_users']++;
                $report['errors'][] = "Error updating user $userName (ID: $userId): " . $e->getMessage();
            }
        }
    }

    return $report;
}

/**
 * Initialize permissions for a specific user
 * 
 * @param int $userId - The user ID
 * @return array - Status report
 */
function initializeUserPermissions($userId) {
    
    $report = [
        'success' => false,
        'message' => '',
        'user_id' => $userId,
        'role' => null,
        'permissions_granted' => 0
    ];

    try {
        // Get user's role
        $userQuery = Database::search(
            "SELECT `u`.`user_fname`, `u`.`user_lname`, `r`.`role_name` 
             FROM `users` `u`
             INNER JOIN `roles` `r` ON `u`.`roles_role_id` = `r`.`role_id`
             WHERE `u`.`user_id` = '" . $userId . "'"
        );

        if ($userQuery->num_rows === 0) {
            $report['message'] = 'User not found';
            return $report;
        }

        $userData = $userQuery->fetch_assoc();
        $roleName = $userData['role_name'];
        $report['role'] = $roleName;

        // Initialize permissions
        $result = PermissionManager::initializeDefaultPermissionsForUser($userId, $roleName);

        if ($result) {
            $perms = PermissionManager::getUserPermissions($userId);
            $report['success'] = true;
            $report['message'] = "Permissions initialized successfully for {$userData['user_fname']} {$userData['user_lname']}";
            $report['permissions_granted'] = array_reduce($perms, function($carry, $item) {
                return $carry + count($item);
            }, 0);
        } else {
            $report['message'] = 'Failed to update user permissions in database';
        }
    } catch (Exception $e) {
        $report['message'] = 'Error: ' . $e->getMessage();
    }

    return $report;
}

/**
 * Get initialization status report
 * 
 * @return array - Status report
 */
function getPermissionInitializationStatus() {
    
    $status = [
        'total_users' => 0,
        'users_with_permissions' => 0,
        'users_without_permissions' => 0,
        'by_role' => []
    ];

    // Get all users and check permission status
    $usersQuery = Database::search(
        "SELECT `u`.`user_id`, `r`.`role_name`, `u`.`user_permissions` 
         FROM `users` `u`
         INNER JOIN `roles` `r` ON `u`.`roles_role_id` = `r`.`role_id`
         ORDER BY `r`.`role_name`"
    );

    $status['total_users'] = $usersQuery->num_rows;

    if ($usersQuery->num_rows > 0) {
        
        while ($userData = $usersQuery->fetch_assoc()) {
            
            $roleName = $userData['role_name'];
            
            if (!isset($status['by_role'][$roleName])) {
                $status['by_role'][$roleName] = [
                    'total' => 0,
                    'with_permissions' => 0,
                    'without_permissions' => 0
                ];
            }

            $status['by_role'][$roleName]['total']++;

            if (!empty($userData['user_permissions'])) {
                $status['users_with_permissions']++;
                $status['by_role'][$roleName]['with_permissions']++;
            } else {
                $status['users_without_permissions']++;
                $status['by_role'][$roleName]['without_permissions']++;
            }
        }
    }

    return $status;
}

/**
 * Reset all user permissions to defaults
 * WARNING: This will overwrite any custom permissions!
 * 
 * @return array - Report
 */
function resetAllPermissionsToDefaults() {
    
    $report = [
        'message' => 'WARNING: This will overwrite all custom permissions!',
        'confirmed' => false,
        'total_reset' => 0
    ];

    // This function is intentionally separated so it requires explicit confirmation
    // To use, call with proper authorization checks
    
    return $report;
}

/**
 * Validate user permissions against role defaults
 * 
 * @param int $userId - The user ID
 * @return array - Validation results
 */
function validateUserPermissions($userId) {
    
    $validation = [
        'user_id' => $userId,
        'valid' => false,
        'message' => '',
        'differences' => []
    ];

    try {
        // Get user's role
        $userQuery = Database::search(
            "SELECT `r`.`role_name` FROM `users` `u`
             INNER JOIN `roles` `r` ON `u`.`roles_role_id` = `r`.`role_id`
             WHERE `u`.`user_id` = '" . $userId . "'"
        );

        if ($userQuery->num_rows === 0) {
            $validation['message'] = 'User not found';
            return $validation;
        }

        $userData = $userQuery->fetch_assoc();
        $roleName = $userData['role_name'];

        // Compare with defaults
        $differences = PermissionManager::compareWithDefaults($userId, $roleName);

        if (empty($differences['missing']) && empty($differences['extra'])) {
            $validation['valid'] = true;
            $validation['message'] = 'User permissions match role defaults';
        } else {
            $validation['valid'] = false;
            $validation['differences'] = $differences;
            
            if (!empty($differences['missing'])) {
                $validation['message'] = 'User is missing some permissions';
            }
            if (!empty($differences['extra'])) {
                if (!empty($validation['message'])) {
                    $validation['message'] .= ' and has extra permissions';
                } else {
                    $validation['message'] = 'User has extra permissions';
                }
            }
        }
    } catch (Exception $e) {
        $validation['message'] = 'Error: ' . $e->getMessage();
    }

    return $validation;
}

// ============================================================================
// API ENDPOINT HANDLERS (if called via HTTP)
// ============================================================================

if (php_sapi_name() !== 'cli' && isset($_GET['action'])) {
    
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    
    try {
        
        switch ($action) {
            
            case 'initialize_all':
                // Initialize all users
                $result = initializeAllUserPermissions();
                echo json_encode($result);
                break;
            
            case 'initialize_user':
                // Initialize specific user
                if (!isset($_GET['user_id'])) {
                    echo json_encode(['error' => 'user_id parameter required']);
                    break;
                }
                $result = initializeUserPermissions($_GET['user_id']);
                echo json_encode($result);
                break;
            
            case 'status':
                // Get status
                $status = getPermissionInitializationStatus();
                echo json_encode($status);
                break;
            
            case 'validate':
                // Validate specific user
                if (!isset($_GET['user_id'])) {
                    echo json_encode(['error' => 'user_id parameter required']);
                    break;
                }
                $result = validateUserPermissions($_GET['user_id']);
                echo json_encode($result);
                break;
            
            default:
                echo json_encode(['error' => 'Unknown action: ' . $action]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}

?>
