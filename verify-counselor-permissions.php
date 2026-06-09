<?php
/**
 * Counselor Permissions Verification Script
 * 
 * This script verifies that all required permissions are set up correctly
 * for the Counselor role and shows the status of all Counselor users
 */

require_once("./includes/connection.php");
require_once("./includes/UserClass.php");

// Set timezone
date_default_timezone_set('Asia/Colombo');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Counselor Permissions Verification</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            font-size: 20px;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .status-card {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .status-card.success {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .status-card.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        .status-card.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .status-icon {
            font-weight: bold;
            margin-right: 10px;
        }
        .status-card.success .status-icon::before {
            content: "✓";
            color: #28a745;
        }
        .status-card.warning .status-icon::before {
            content: "⚠";
            color: #ffc107;
        }
        .status-card.error .status-icon::before {
            content: "✗";
            color: #dc3545;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table th {
            background: #f0f0f0;
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
        }
        table td {
            border: 1px solid #ddd;
            padding: 12px;
        }
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
        table tr:hover {
            background: #f0f0f0;
        }
        .permission-list {
            list-style: none;
            columns: 2;
            gap: 30px;
        }
        .permission-list li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
        }
        .permission-list li::before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            position: absolute;
            left: 0;
        }
        .no-data {
            text-align: center;
            color: #999;
            padding: 20px;
            font-style: italic;
        }
        .btn-group {
            margin-top: 20px;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stat-box .label {
            font-size: 14px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Counselor Permissions Verification</h1>
            <p>System Status Check - Last Updated: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>

        <div class="content">
            <?php
            // Check required permissions exist
            $defaultPermissionIds = [10, 76, 47, 9];
            $requiredPermissions = [
                10 => 'Add new inquiry',
                76 => 'View student payments',
                47 => 'View time table',
                9 => 'View academic status',
            ];

            echo '<div class="section">';
            echo '<h2>Default Permissions Status</h2>';

            $missingPermissions = [];
            foreach ($defaultPermissionIds as $permId) {
                $permQuery = Database::search("SELECT `permission_to_id`, `permissions`.`permission` 
                                              FROM `permission_to`
                                              INNER JOIN `permissions` ON `permission_to`.`permissions_permission_id` = `permissions`.`permission_id`
                                              WHERE `permission_to_id` = '" . $permId . "'");
                
                if ($permQuery->num_rows > 0) {
                    $permData = $permQuery->fetch_assoc();
                    echo '<div class="status-card success">';
                    echo '<span class="status-icon"></span>';
                    echo '<strong>' . $permData['permission'] . '</strong> (ID: ' . $permId . ')';
                    echo '</div>';
                } else {
                    echo '<div class="status-card error">';
                    echo '<span class="status-icon"></span>';
                    echo '<strong>Missing Permission ID ' . $permId . '</strong>';
                    echo '</div>';
                    $missingPermissions[] = $permId;
                }
            }

            if (!empty($missingPermissions)) {
                echo '<div class="status-card warning" style="margin-top: 15px;">';
                echo '<strong>Action Required:</strong> Run the migration script to create missing permissions: ';
                echo 'http://your-domain/AIC-Dashboard/migrate-counselor-permissions.php';
                echo '</div>';
            }
            echo '</div>';

            // Get Counselor user statistics
            $counselorQuery = Database::search("
                SELECT COUNT(*) as count
                FROM `users`
                INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                WHERE `user_roles`.`user_role` = 'Counselor'
            ");
            $counselorCount = $counselorQuery->fetch_assoc()['count'];

            // Get Counselor users with default permissions
            $counselorWithPermsQuery = Database::search("
                SELECT DISTINCT `users`.`user_id`
                FROM `users`
                INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                INNER JOIN `users_has_permission_to_other` ON `users`.`user_id` = `users_has_permission_to_other`.`users_user_id`
                WHERE `user_roles`.`user_role` = 'Counselor'
                AND `users_has_permission_to_other`.`permission_to_permission_to_id` IN (" . implode(',', $defaultPermissionIds) . ")
                AND `users_has_permission_to_other`.`status_status_id` = 1
            ");
            $counselorWithPermsCount = $counselorWithPermsQuery->num_rows;

            echo '<div class="section">';
            echo '<h2>Counselor Users Overview</h2>';
            echo '<div class="stats-grid">';
            echo '<div class="stat-box">';
            echo '<div class="number">' . $counselorCount . '</div>';
            echo '<div class="label">Total Counselor Users</div>';
            echo '</div>';
            echo '<div class="stat-box">';
            echo '<div class="number">' . $counselorWithPermsCount . '</div>';
            echo '<div class="label">With Default Permissions</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            // List all Counselor users and their permissions
            echo '<div class="section">';
            echo '<h2>Detailed Counselor Permissions</h2>';

            $allCounselorsQuery = Database::search("
                SELECT `users`.`user_id`, `users`.`user_fname`, `users`.`user_lname`, `users`.`user_email`, `users`.`status_status_id`
                FROM `users`
                INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                WHERE `user_roles`.`user_role` = 'Counselor'
                ORDER BY `users`.`user_fname`, `users`.`user_lname`
            ");

            if ($allCounselorsQuery->num_rows > 0) {
                echo '<table>';
                echo '<thead>';
                echo '<tr>';
                echo '<th>User ID</th>';
                echo '<th>Name</th>';
                echo '<th>Email</th>';
                echo '<th>Status</th>';
                echo '<th>Default Permissions</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';

                while ($counselor = $allCounselorsQuery->fetch_assoc()) {
                    $userPermsQuery = Database::search("
                        SELECT COUNT(*) as count
                        FROM `users_has_permission_to_other`
                        WHERE `users_user_id` = '" . $counselor['user_id'] . "'
                        AND `permission_to_permission_to_id` IN (" . implode(',', $defaultPermissionIds) . ")
                        AND `status_status_id` = 1
                    ");
                    $userPermsCount = $userPermsQuery->fetch_assoc()['count'];
                    $hasAllPerms = ($userPermsCount == count($defaultPermissionIds));

                    echo '<tr>';
                    echo '<td>#' . $counselor['user_id'] . '</td>';
                    echo '<td>' . htmlspecialchars($counselor['user_fname'] . ' ' . $counselor['user_lname']) . '</td>';
                    echo '<td>' . htmlspecialchars($counselor['user_email']) . '</td>';
                    echo '<td>' . ($counselor['status_status_id'] == 1 ? '<span style="color:green;">Active</span>' : '<span style="color:red;">Inactive</span>') . '</td>';
                    echo '<td>';
                    if ($hasAllPerms) {
                        echo '<span style="color:green; font-weight:bold;">✓ All Assigned (' . $userPermsCount . '/' . count($defaultPermissionIds) . ')</span>';
                    } else {
                        echo '<span style="color:orange; font-weight:bold;">⚠ Partial (' . $userPermsCount . '/' . count($defaultPermissionIds) . ')</span>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<div class="no-data">No Counselor users found in the system.</div>';
            }

            echo '</div>';

            // System recommendations
            echo '<div class="section">';
            echo '<h2>System Recommendations</h2>';

            $recommendations = [];

            if (!empty($missingPermissions)) {
                $recommendations[] = [
                    'type' => 'warning',
                    'text' => 'Run the migration script to create missing default permissions',
                    'action' => '<a href="migrate-counselor-permissions.php" class="btn btn-primary">Run Migration</a>'
                ];
            }

            if ($counselorCount > $counselorWithPermsCount) {
                $recommendations[] = [
                    'type' => 'warning',
                    'text' => 'Some Counselor users do not have all default permissions assigned. Re-run migration to update.',
                    'action' => '<a href="migrate-counselor-permissions.php" class="btn btn-primary">Run Migration</a>'
                ];
            } else if ($counselorCount > 0) {
                $recommendations[] = [
                    'type' => 'success',
                    'text' => 'All Counselor users have the required default permissions assigned.',
                    'action' => ''
                ];
            }

            if (empty($recommendations)) {
                $recommendations[] = [
                    'type' => 'success',
                    'text' => 'System is properly configured. All checks passed.',
                    'action' => ''
                ];
            }

            foreach ($recommendations as $rec) {
                echo '<div class="status-card ' . $rec['type'] . '">';
                echo '<span class="status-icon"></span>';
                echo '<strong>' . $rec['text'] . '</strong>';
                if (!empty($rec['action'])) {
                    echo '<br>' . $rec['action'];
                }
                echo '</div>';
            }

            echo '</div>';
            ?>
        </div>
    </div>
</body>
</html>
