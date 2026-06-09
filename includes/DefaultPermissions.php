<?php
/**
 * Default User Role Permissions Configuration
 * 
 * This file defines the default permissions for each user role in the LMS system.
 * Each role has an array of permissions that should be assigned by default.
 * 
 * Permission Structure:
 * 'module_name' => [
 *     'permission_id_1' => 'permission_name',
 *     'permission_id_2' => 'permission_name',
 *     ...
 * ]
 */

$DEFAULT_ROLE_PERMISSIONS = [
    
    // COUNSELOR ROLE
    'Counselor' => [
        'students' => [1, 2],  // Add new student, Upload Documents
        'payments' => [1, 2, 3],  // View student payments, View pending payments, Upload payment slip
        'timetable' => [1],  // View time table
        'academic' => [1],  // View academic status
    ],

    // ACCOUNTS ROLE
    'Accounts' => [
        'payments' => [1, 2, 3, 4, 5, 6],  // All payment related fields
        'reports' => [1, 2],  // Payment reports
    ],

    // ACADEMIC ADMIN ROLE
    'Academic Admin' => [
        'students' => [1, 3, 4],  // Add student, Edit student details, View student details
        'documents' => [1, 2],  // Check documents, Approve/Reject documents
        'payments' => [1, 2],  // View student payments, View pending payments
        'timetable' => [1],  // View time table
        'academic' => [1],  // View academic status
        'certificates' => [1, 2, 3, 4],  // Create admission letters, transcripts, certificates, student ID
    ],

    // ACADEMIC ROLE
    'Academic' => [
        'programs' => [1, 2],  // Create program, Edit program
        'batch' => [1, 2],  // Create batch, Edit batch
        'timetable' => [1, 2],  // View time table, Create time table
        'payments' => [1, 2],  // View student payments, View pending payments
        'marks' => [1, 2],  // Enter student marks, Edit student marks
        'academic' => [1],  // View academic status
        'assignments' => [1, 2],  // Create assignments, Edit assignments
        'exams' => [1, 2],  // Create exams, Edit exams
        'attendance' => [1],  // View attendance
    ],

    // LECTURER ROLE
    'Lecturer' => [
        'timetable' => [1],  // View time table
        'assignments' => [1],  // Create assignments
        'marks' => [1, 2],  // Enter marks, Edit marks
        'attendance' => [1],  // View attendance
    ],

    // BRANCH MANAGER ROLE
    'Branch Manager' => [
        'students' => [1, 2],  // Add new student, Upload Documents
        'payments' => [1, 2, 3],  // View student payments, View pending payments, Upload payment slip
        'timetable' => [1],  // View time table
        'academic' => [1],  // View academic status
        'counselors' => [1],  // View details of all counselors under him
        'reports' => [1, 2],  // View branch revenue, View enrollment numbers
    ],

    // HEAD OF ADMISSION ROLE
    'Head of Admission' => [
        'students' => [1, 2],  // Add new student, Upload Documents
        'payments' => [1, 2, 3],  // View student payments, View pending payments, Upload payment slip
        'timetable' => [1],  // View time table
        'academic' => [1],  // View academic status
        'counselors' => [1],  // View details of all counselors
        'reports' => [1, 2, 3],  // View branch revenue, View total revenue, Enrollment numbers
    ],

    // DIRECTOR ROLE
    'Director' => [
        'reports' => [1, 2, 3, 4],  // Enrollment students branch-wise, total enrollment, Revenue branch-wise, total revenue
    ],

];

/**
 * Permission Module Definitions
 * 
 * Maps module names to their permission IDs and descriptions
 */
$PERMISSION_MODULES = [
    
    'students' => [
        1 => 'Add new student',
        2 => 'Upload Documents',
        3 => 'Edit student details',
        4 => 'View student details',
        5 => 'Delete student',
        6 => 'Manage student permissions',
    ],

    'payments' => [
        1 => 'View student payments',
        2 => 'View student pending payments',
        3 => 'Upload registration fee slip',
        4 => 'Upload first payment slip',
        5 => 'Process payment',
        6 => 'Refund payment',
    ],

    'documents' => [
        1 => 'Check documents',
        2 => 'Approve/Reject documents',
        3 => 'View documents',
        4 => 'Delete documents',
    ],

    'timetable' => [
        1 => 'View time table',
        2 => 'Create time table',
        3 => 'Edit time table',
        4 => 'Delete time table',
    ],

    'academic' => [
        1 => 'View academic status',
        2 => 'Edit academic status',
        3 => 'View academic report',
    ],

    'marks' => [
        1 => 'Enter student marks',
        2 => 'Edit student marks',
        3 => 'View student marks',
        4 => 'Delete marks',
    ],

    'assignments' => [
        1 => 'Create assignments/Exams',
        2 => 'Edit assignments/Exams',
        3 => 'View assignments/Exams',
        4 => 'Delete assignments/Exams',
        5 => 'Mark assignments',
    ],

    'exams' => [
        1 => 'Create exams',
        2 => 'Edit exams',
        3 => 'View exams',
        4 => 'Delete exams',
    ],

    'attendance' => [
        1 => 'View attendance',
        2 => 'Mark attendance',
        3 => 'Edit attendance',
    ],

    'programs' => [
        1 => 'Create program',
        2 => 'Edit program',
        3 => 'View program',
        4 => 'Delete program',
    ],

    'batch' => [
        1 => 'Create batch',
        2 => 'Edit batch',
        3 => 'View batch',
        4 => 'Delete batch',
    ],

    'certificates' => [
        1 => 'Create Admission letters',
        2 => 'Create Academic transcripts',
        3 => 'Create Certificates',
        4 => 'Create Student ID',
    ],

    'counselors' => [
        1 => 'View details of all counselors',
        2 => 'Manage counselors',
        3 => 'View counselor reports',
    ],

    'reports' => [
        1 => 'View branch revenue',
        2 => 'View enrollment numbers',
        3 => 'View total revenue',
        4 => 'View detailed reports',
    ],

];

/**
 * Get default permissions for a specific role
 * 
 * @param string $role - The role name
 * @return array - Array of modules and their permission IDs
 */
function getDefaultPermissionsForRole($role) {
    global $DEFAULT_ROLE_PERMISSIONS;
    
    if (isset($DEFAULT_ROLE_PERMISSIONS[$role])) {
        return $DEFAULT_ROLE_PERMISSIONS[$role];
    }
    
    return [];
}

/**
 * Get all roles
 * 
 * @return array - Array of all role names
 */
function getAllRoles() {
    global $DEFAULT_ROLE_PERMISSIONS;
    return array_keys($DEFAULT_ROLE_PERMISSIONS);
}

/**
 * Get all permission modules
 * 
 * @return array - Array of all modules with their permissions
 */
function getAllPermissionModules() {
    global $PERMISSION_MODULES;
    return $PERMISSION_MODULES;
}

/**
 * Check if a role exists in default permissions
 * 
 * @param string $role - The role name
 * @return boolean
 */
function roleExists($role) {
    global $DEFAULT_ROLE_PERMISSIONS;
    return isset($DEFAULT_ROLE_PERMISSIONS[$role]);
}

/**
 * Get permission description
 * 
 * @param string $module - The module name
 * @param int $permissionId - The permission ID
 * @return string - Permission description
 */
function getPermissionDescription($module, $permissionId) {
    global $PERMISSION_MODULES;
    
    if (isset($PERMISSION_MODULES[$module][$permissionId])) {
        return $PERMISSION_MODULES[$module][$permissionId];
    }
    
    return 'Unknown Permission';
}

?>
