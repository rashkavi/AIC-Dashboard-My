<?php
/**
 * Database Migration: Add Missing Counselor Permissions
 * 
 * This script adds the missing permissions required for Counselor role:
 * - Upload Documents
 * - Upload Registration Fee Slip
 * - Upload First Payment Slip
 * - View Student Pending Payments
 * 
 * Run this script once to initialize the database with these new permissions
 */

require_once("./includes/connection.php");
require_once("./api/setDefaultCounselorPermissions.php");

// Get current directory for logging
$logFile = __DIR__ . '/logs/migration_' . date('Y-m-d_H-i-s') . '.log';
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

function logMessage($message)
{
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n", FILE_APPEND);
    echo $message . "\n";
}

logMessage("=== Starting Counselor Permissions Migration ===");

try {
    // Create missing permissions
    DefaultCounselorPermissions::createMissingPermissions();
    logMessage("✓ Missing permissions created successfully");

    // Get all Counselor users and assign default permissions
    $counselorQuery = Database::search("
        SELECT `users`.`user_id`, `user_roles`.`user_role` 
        FROM `users`
        INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
        WHERE `user_roles`.`user_role` = 'Counselor'
    ");

    $counselorCount = $counselorQuery->num_rows;
    logMessage("Found " . $counselorCount . " Counselor user(s)");

    if ($counselorCount > 0) {
        while ($counselor = $counselorQuery->fetch_assoc()) {
            DefaultCounselorPermissions::assignDefaultPermissions($counselor['user_id'], 'Counselor');
            logMessage("✓ Permissions assigned to Counselor ID: " . $counselor['user_id']);
        }
    }

    logMessage("=== Migration Completed Successfully ===");
    logMessage("All Counselor users now have default permissions:");
    logMessage("  - Add new inquiry");
    logMessage("  - View student payments");
    logMessage("  - View time table");
    logMessage("  - View academic status");
    logMessage("  - Upload Documents");
    logMessage("  - Upload Registration Fee Slip");
    logMessage("  - Upload First Payment Slip");
    logMessage("  - View Student Pending Payments");

} catch (Exception $e) {
    logMessage("✗ Error: " . $e->getMessage());
}

// Display completion message
?>
<!DOCTYPE html>
<html>
<head>
    <title>Counselor Permissions Migration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .info {
            color: #004085;
            background-color: #d1ecf1;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        li {
            margin: 8px 0;
        }
        .log-file {
            color: #666;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Counselor Permissions Migration</h1>
        <div class="success">
            ✓ Migration completed successfully!
        </div>
        
        <div class="info">
            <strong>Default Permissions Added:</strong>
            <ul>
                <li>Add new inquiry</li>
                <li>View student payments</li>
                <li>View time table</li>
                <li>View academic status</li>
                <li>Upload Documents</li>
                <li>Upload Registration Fee Slip</li>
                <li>Upload First Payment Slip</li>
                <li>View Student Pending Payments</li>
            </ul>
        </div>

        <div class="info">
            <strong>Next Steps:</strong>
            <ul>
                <li>All new Counselor users will automatically receive these permissions when created</li>
                <li>Existing Counselor users have been updated with default permissions</li>
                <li>You can now safely delete this migration file</li>
            </ul>
        </div>

        <div class="log-file">
            <strong>Migration Log:</strong><br>
            Log file: <?php echo $logFile; ?><br>
            You can review the detailed migration log in the logs folder
        </div>
    </div>
</body>
</html>
