# Permission_to Table Analysis Report

## Summary
The permissions you're looking for with those exact names don't exist in the current system. The permission system uses generic permission names that apply across different modules. Below is what I found and the closest matches.

## Permission System Structure

The system uses:
- **Permissions**: Generic permission actions (permission_id)
- **Permission_to**: Module-specific permissions linking permissions to permission types (permission_to_id)
- **Permission_Type**: The modules/areas where permissions apply (permission_type_id)

## Available Generic Permissions (permission_id)
```
1  = View Only
2  = Add New
3  = Reset Password
4  = Active/Inactive
5  = Give Permission
6  = Edit Permission
7  = Assign Intake
8  = Edit Intake
9  = Remove Intake
10 = Update
11 = Delete
12 = Add Payment Plan
13 = Request Payment
14 = Enable Card Payment
15 = Approve / Reject Payment
17 = Release Mark
18 = Enable Late Submission
19 = Enable Re-Correction
20 = Upload Marks (CSV)
22 = Add Bank
23 = Edit Bank
24 = Upload Payment Receipt
25 = Mark Attendance
```

## Permission Types and Their IDs
```
1  = User
2  = Student
3  = Faculty
4  = Programme
5  = Intake
6  = Level
7  = Subject
8  = Intake Students
9  = Lecturers
10 = Intake Payments
11 = Lecture Schedule
12 = Pre-Recorded Lectures
13 = Lecture Recordings
14 = Subject Students
15 = Exam
16 = Assignments
17 = Quiz
18 = Subject Overview
19 = Payments
20 = FAQ
21 = Branch
22 = Attendance
23 = Incidents
```

## Mapping: What You're Looking For

Based on the code in `inquiries.php`, here are the permissions being used:

### 1. Add new inquiry
- **Code Used**: `UserPermissions::hasPermission('students', 2)`
- **permission_to_id**: 10
- **Type**: Student (Type 2)
- **Permission**: Add New
- **Actual Name**: "Add New" under Student module

### 2. Upload Documents
- **Code Used**: None found for generic document upload
- **Closest Match**: `permission_id=24` = "Upload Payment Receipt" 
- **Note**: This is specifically for payment receipts, not general documents

### 3. Upload registration fee slip
- **Code Used**: Not found as a specific permission
- **Status**: Not implemented as a separate permission
- **Note**: May be handled through document upload flow

### 4. Upload first payment slip
- **Code Used**: Not found as a specific permission
- **Status**: Not implemented as a separate permission
- **Note**: May be handled through document upload flow

### 5. View student payments
- **Code Used**: `UserPermissions::hasPermission('payments', 1)` OR similar
- **permission_to_id**: 76
- **Type**: Payments (Type 19)
- **Permission**: View Only

### 6. View student pending payments
- **Code Used**: Not found as a specific permission
- **Status**: Uses generic "View Only" for Payments
- **permission_to_id**: 76
- **Note**: Likely filtered in code logic, not a separate permission

### 7. View time table
- **Closest Match**: `UserPermissions::hasPermission('lecture_schedule', 1)`
- **permission_to_id**: 47
- **Type**: Lecture Schedule (Type 11)
- **Permission**: View Only

### 8. View academic status
- **Code Used**: Not found as a specific permission
- **Status**: Not implemented as a separate permission
- **Note**: May be part of general Student viewing permissions
- **Closest**: `UserPermissions::hasPermission('students', 1)`
- **permission_to_id**: 9
- **Type**: Student (Type 2)
- **Permission**: View Only

## Complete permission_to Table for Type 2 (Student)

These are the actual permissions for the Student module where "Add new inquiry" is used:

```
permission_to_id=9,  permission_id=1,  permission=View Only          (Type: Student)
permission_to_id=10, permission_id=2,  permission=Add New            (Type: Student)
permission_to_id=11, permission_id=3,  permission=Reset Password     (Type: Student)
permission_to_id=12, permission_id=4,  permission=Active/Inactive    (Type: Student)
permission_to_id=13, permission_id=7,  permission=Assign Intake      (Type: Student)
permission_to_id=14, permission_id=8,  permission=Edit Intake        (Type: Student)
permission_to_id=15, permission_id=10, permission=Update             (Type: Student)
permission_to_id=16, permission_id=11, permission=Delete             (Type: Student)
```

## How Permissions Are Checked in Code

Example from `inquiries.php`:
```php
if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('students', 2)) {
    // Show "Add New Inquiry" button
}
```

The method signature is:
```php
public static function hasPermission($type, $permissionId, $permission_type_related_id = null): bool
```

Where:
- `$type` = the module name ('students', 'users', 'payments', 'lecture_schedule', etc.)
- `$permissionId` = the permission_id value (1, 2, 10, etc.)
- `$permission_type_related_id` = optional, for scoping to specific areas

## Recommendation

The permissions you're looking for appear to be **not yet created in the system**. You would need to:

1. **Add new permissions** to the `permissions` table for:
   - Upload Documents
   - Upload Registration Fee Slip
   - Upload First Payment Slip
   - View Student Pending Payments
   - View Academic Status

2. **Create permission_to mappings** linking these new permissions to the appropriate permission types

3. **Update the code** in inquiry/payment related files to check for these new permission IDs

Example SQL to add a new permission:
```sql
INSERT INTO `permissions` (`permission_id`, `permission`) 
VALUES (26, 'Upload Documents');

INSERT INTO `permission_to` 
(`permissions_permission_id`, `permission_type_permission_type_id`) 
VALUES (26, 8);  -- For Intake Students
```

---

**Note**: The actual permissions currently in the system are generic and reusable across modules. The specific permission naming might be handled differently in your implementation.
