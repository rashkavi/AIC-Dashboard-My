# Counselor Default Permissions Setup

## Overview
This update automatically assigns default permissions to the Counselor user role. When a new Counselor user is created, they will automatically receive the necessary permissions without manual assignment.

## Permissions Assigned to Counselor

The following permissions are now automatically assigned to all Counselor users:

1. **Add new inquiry** - Allows counselors to create new student inquiries
2. **View student payments** - Allows counselors to view student payment records
3. **View time table** - Allows counselors to view lecture schedules and time tables
4. **View academic status** - Allows counselors to view student academic information
5. **Upload Documents** - Allows counselors to upload student documents *(newly created)*
6. **Upload Registration Fee Slip** - Allows counselors to upload registration payment proofs *(newly created)*
7. **Upload First Payment Slip** - Allows counselors to upload payment receipts *(newly created)*
8. **View Student Pending Payments** - Allows counselors to view pending payment information *(newly created)*

## Files Modified/Created

### 1. **api/setDefaultCounselorPermissions.php** (NEW)
Contains the `DefaultCounselorPermissions` class that handles:
- Getting default permission list
- Assigning permissions to users
- Creating missing permission records in database

### 2. **api/addUser.php** (MODIFIED)
Updated to:
- Include the `setDefaultCounselorPermissions.php` file
- Automatically call `DefaultCounselorPermissions::assignDefaultPermissions()` when creating a Counselor user

### 3. **migrate-counselor-permissions.php** (NEW)
One-time migration script that:
- Creates missing permission records in the database
- Assigns default permissions to all existing Counselor users

## Installation Instructions

### Step 1: Run the Migration (ONE TIME ONLY)
1. Open your browser and navigate to: `http://your-domain/AIC-Dashboard/migrate-counselor-permissions.php`
2. This script will:
   - Create any missing permissions in the database
   - Assign default permissions to all existing Counselor users
   - Generate a migration log file
3. After completion, you should see a success message

### Step 2: Verify Installation
1. Create a new Counselor user through the admin dashboard
2. Go to "Users Permissions" section
3. Search for the newly created Counselor user
4. You should see that the default permissions are already assigned

## How It Works

### For New Counselor Users:
1. Admin creates a new user with role "Counselor"
2. Upon user creation (in `api/addUser.php`):
   - The system identifies the user role as "Counselor"
   - Automatically calls `DefaultCounselorPermissions::assignDefaultPermissions()`
   - Default permissions are assigned instantly
3. User is ready to use without manual permission assignment

### Database Changes:
New permissions are added to the `permission_to` table:
- Linked to the appropriate `permission_type` (students, payments)
- Associated with base permissions in the `permissions` table
- Automatically assigned to users when adding or via migration

## Permission Structure

### Permission Types Used:
- **Type 1**: Users/General permissions
- **Type 2**: Students permissions
- **Type 19**: Payments permissions
- **Type 11**: Lecture Schedule permissions

### Database Tables:
- `permission_to` - Defines available permissions with IDs
- `permissions` - Base permission definitions
- `users_has_permission_to_other` - Links users to permissions

## Reverting Changes (If Needed)

If you need to revert these changes:

1. **Remove Auto-Assignment:**
   - Edit `api/addUser.php`
   - Remove the lines that include and call `DefaultCounselorPermissions`

2. **Keep Permissions in Database:**
   - The new permission records will remain in the database
   - Existing assignments remain active

3. **Remove Old Assignments:**
   - Run: `DELETE FROM users_has_permission_to_other WHERE permission_to_permission_to_id IN (SELECT permission_to_id FROM permission_to WHERE permission_type_permission_type_id IN (2, 19) AND ...)`
   - This requires identifying the specific permission IDs

## Troubleshooting

### Issue: Migration script shows errors
**Solution:**
1. Verify database connection is working
2. Check that the `connection.php` file exists in the includes folder
3. Ensure the logs folder exists and has write permissions

### Issue: New Counselor users don't have permissions
**Solution:**
1. Check if migration was run successfully
2. Verify that `api/addUser.php` includes the new permission assignment code
3. Run migration script again: `migrate-counselor-permissions.php`

### Issue: Missing permissions showing as unavailable
**Solution:**
1. The missing permissions might not exist in the database
2. Re-run the migration script: `migrate-counselor-permissions.php`
3. This will create any missing permission records

## API Reference

### DefaultCounselorPermissions Class

```php
// Get list of default permission IDs
DefaultCounselorPermissions::getDefaultPermissions();

// Assign permissions to a user
DefaultCounselorPermissions::assignDefaultPermissions($userId, "Counselor");

// Create missing permissions in database
DefaultCounselorPermissions::createMissingPermissions();
```

## Support

For issues or questions:
1. Check the migration log file in `/logs/` folder
2. Verify database structure matches expectations
3. Ensure all required files are in place

## Version Information
- Created: June 2024
- Status: Active
- Scope: Counselor Role Default Permissions
