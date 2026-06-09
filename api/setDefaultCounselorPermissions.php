<?php
/**
 * Set Default Permissions for Counselor Role
 * 
 * This API assigns default permissions to newly created Counselor users
 * Default permissions include:
 * - Add new inquiry (permission_to_id: 10)
 * - View student payments (permission_to_id: 76)
 * - View time table (permission_to_id: 47)
 * - View academic status (permission_to_id: 9)
 * - Upload Documents (permission_to_id: will be created if missing)
 * - Upload registration fee slip (permission_to_id: will be created if missing)
 * - Upload first payment slip (permission_to_id: will be created if missing)
 * - View student pending payments (permission_to_id: will be created if missing)
 */

require_once("../includes/connection.php");

class DefaultCounselorPermissions
{
    /**
     * Define default permissions for Counselor role
     * Returns array of permission_to_ids
     */
    public static function getDefaultPermissions()
    {
        return [
            10,  // Add new inquiry (students)
            76,  // View student payments (payments)
            47,  // View time table (lecture_schedule)
            9,   // View academic status (students)
            // Additional permissions to be created:
            // Upload Documents
            // Upload registration fee slip
            // Upload first payment slip
            // View student pending payments
        ];
    }

    /**
     * Assign default permissions to a user
     * @param int $userId The user ID to assign permissions to
     * @param string $userRole The user role (should be "Counselor")
     * @return bool Success status
     */
    public static function assignDefaultPermissions($userId, $userRole = "Counselor")
    {
        if ($userRole !== "Counselor") {
            return false;
        }

        $permissions = self::getDefaultPermissions();
        $timestamp = date('Y-m-d H:i:s', time());
        
        // Get admin/system user ID - use 1 as fallback
        $addedBy = 1;
        
        foreach ($permissions as $permissionToId) {
            // Check if permission already exists
            $checkQuery = "SELECT `permission_to_permission_to_id` FROM `users_has_permission_to_other`
                          WHERE `users_user_id` = '" . $userId . "' 
                          AND `permission_to_permission_to_id` = '" . $permissionToId . "'";
            
            $result = Database::search($checkQuery);
            
            if ($result->num_rows == 0) {
                // Insert the permission
                $insertQuery = "INSERT INTO `users_has_permission_to_other` 
                               (`users_user_id`, `permission_to_permission_to_id`, `added_by`, `status_status_id`, `added_at`)
                               VALUES ('" . $userId . "', '" . $permissionToId . "', '" . $addedBy . "', '1', '" . $timestamp . "')";
                
                Database::iud($insertQuery);
            }
        }
        
        return true;
    }

    /**
     * Create missing permissions for Counselor if they don't exist
     * This should be run once during setup or migration
     */
    public static function createMissingPermissions()
    {
        // Check if permissions table structure matches expectations
        $missingPermissions = [
            [
                'permission_name' => 'Upload Documents',
                'permission_type_id' => 2,  // Student type
                'permission_icon' => 'mdi:file-upload'
            ],
            [
                'permission_name' => 'Upload Registration Fee Slip',
                'permission_type_id' => 19,  // Payments type
                'permission_icon' => 'mdi:receipt'
            ],
            [
                'permission_name' => 'Upload First Payment Slip',
                'permission_type_id' => 19,  // Payments type
                'permission_icon' => 'mdi:receipt'
            ],
            [
                'permission_name' => 'View Student Pending Payments',
                'permission_type_id' => 19,  // Payments type
                'permission_icon' => 'mdi:clock-alert'
            ]
        ];

        foreach ($missingPermissions as $perm) {
            // Check if permission already exists by name
            $checkQuery = "SELECT `permissions`.`permission_id` FROM `permissions`
                          WHERE `permission` = '" . $perm['permission_name'] . "'";
            
            $result = Database::search($checkQuery);
            
            if ($result->num_rows == 0) {
                // Create permission
                $insertPermQuery = "INSERT INTO `permissions` (`permission`)
                                   VALUES ('" . $perm['permission_name'] . "')";
                Database::iud($insertPermQuery);
                
                $permissionId = Database::$connection->insert_id;
                
                // Create permission_to entry
                $insertPermToQuery = "INSERT INTO `permission_to` 
                                     (`permissions_permission_id`, `permission_type_permission_type_id`)
                                     VALUES ('" . $permissionId . "', '" . $perm['permission_type_id'] . "')";
                Database::iud($insertPermToQuery);
            }
        }
    }
}

// If this file is called directly via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = new stdClass();
    
    if (isset($_POST['action']) && $_POST['action'] === 'assignDefault') {
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $userRole = isset($_POST['user_role']) ? trim($_POST['user_role']) : '';
        
        if ($userId > 0 && !empty($userRole)) {
            if (DefaultCounselorPermissions::assignDefaultPermissions($userId, $userRole)) {
                $status->success = true;
                $status->message = 'Default permissions assigned successfully';
            } else {
                $status->success = false;
                $status->message = 'Failed to assign permissions or invalid role';
            }
        } else {
            $status->success = false;
            $status->message = 'Invalid user ID or role';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'createMissing') {
        DefaultCounselorPermissions::createMissingPermissions();
        $status->success = true;
        $status->message = 'Missing permissions created successfully';
    } else {
        $status->success = false;
        $status->message = 'Invalid action';
    }
    
    header('Content-Type: application/json');
    echo json_encode($status);
}
?>
