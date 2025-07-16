# 🔔 Parent Notification System Integration Guide

This guide shows how to integrate automatic parent notifications into your existing school management system.

## 📋 Quick Setup

### 1. Include the Notification System
Add this line to any file where you want to trigger notifications:
```php
require_once 'includes/notification_triggers.php';
```

### 2. Basic Integration Examples

#### 📊 When Adding Student Marks/Grades
```php
// After successfully inserting marks into database
triggerMarksNotification($student_id, $subject, $marks, $total_marks);

// Example:
triggerMarksNotification(1, 'Mathematics', 85, 100);
// Result: "Your child Mary Doe has received 85/100 in Mathematics"
```

#### 📅 When Recording Attendance
```php
// After successfully recording attendance
triggerAttendanceNotification($student_id, $attendance_status, $date, $subject);

// Examples:
triggerAttendanceNotification(1, 'present', '2024-01-20', 'Mathematics');
triggerAttendanceNotification(1, 'absent', '2024-01-20', 'English');
triggerAttendanceNotification(1, 'late', '2024-01-20', 'Science');
```

#### 👤 When Updating Student Information
```php
// After successfully updating student data
triggerStudentUpdateNotification($student_id, $update_type);

// Examples:
triggerStudentUpdateNotification(1, 'profile');
triggerStudentUpdateNotification(1, 'class assignment');
triggerStudentUpdateNotification(1, 'contact information');
```

#### 🗑️ When Deleting/Removing Students
```php
// BEFORE deleting student record
triggerStudentDeleteNotification($student_id);

// Then proceed with deletion
```

## 🎯 Detailed Integration Examples

### Marks/Grades System Integration

```php
function addStudentGrade($student_id, $subject, $marks, $total_marks, $exam_type = 'Test') {
    try {
        $conn = getDbConnection();
        
        // Insert grade into database
        $stmt = $conn->prepare("INSERT INTO grades (student_id, subject, marks, total_marks, exam_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiis", $student_id, $subject, $marks, $total_marks, $exam_type);
        
        if ($stmt->execute()) {
            // ✅ SUCCESS: Grade added to database
            
            // 🔔 TRIGGER NOTIFICATION: Notify parent
            triggerMarksNotification($student_id, $subject, $marks, $total_marks);
            
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error adding grade: " . $e->getMessage());
        return false;
    }
}
```

### Attendance System Integration

```php
function recordAttendance($student_id, $status, $date = null, $subject = null) {
    try {
        if (!$date) $date = date('Y-m-d');
        
        $conn = getDbConnection();
        
        // Insert attendance into database
        $stmt = $conn->prepare("INSERT INTO attendance (student_id, status, date, subject) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $student_id, $status, $date, $subject);
        
        if ($stmt->execute()) {
            // ✅ SUCCESS: Attendance recorded
            
            // 🔔 TRIGGER NOTIFICATION: Notify parent
            triggerAttendanceNotification($student_id, $status, $date, $subject);
            
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error recording attendance: " . $e->getMessage());
        return false;
    }
}
```

### Student Management Integration

```php
function updateStudent($student_id, $update_data) {
    try {
        $conn = getDbConnection();
        
        // Build and execute update query
        // ... your existing update logic ...
        
        if ($update_successful) {
            // ✅ SUCCESS: Student updated
            
            // 🔔 TRIGGER NOTIFICATION: Notify parent
            triggerStudentUpdateNotification($student_id, 'profile');
            
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error updating student: " . $e->getMessage());
        return false;
    }
}
```

## 🔄 Notification Types and Examples

### Academic Notifications
- **New Grade:** "Your child Mary Doe has received 85/100 in Mathematics"
- **Perfect Score:** "Your child John Smith scored 100/100 (100%) in Chemistry"
- **Low Grade:** "Your child Sarah Johnson received 65/100 in Physics"

### Attendance Notifications
- **Present:** "Your child Mary Doe was marked present on Jan 20, 2024 for Mathematics"
- **Absent:** "Your child Mary Doe was marked absent on Jan 20, 2024 for English"
- **Late:** "Your child Mary Doe was marked late on Jan 20, 2024 for Science"
- **Excused:** "Your child Mary Doe was marked excused on Jan 20, 2024"

### Student Management Notifications
- **Profile Update:** "Your child Mary Doe's profile information has been updated"
- **Class Change:** "Your child Mary Doe's class assignment has been updated"
- **Status Change:** "Your child Mary Doe's enrollment status has been updated"

## 🎨 Notification Features

### Visual Indicators
- **Red Bell Icon:** Shows when there are unread notifications
- **Count Badge:** Displays exact number of unread notifications (e.g., "5" or "99+")
- **Color Coding:** Different colors for different notification types
- **Read/Unread Status:** Visual distinction between read and unread notifications

### Interactive Features
- **Click to Read:** Click notifications to mark as read and navigate to relevant page
- **Mark All Read:** Bulk action to mark all notifications as read
- **Delete:** Individual notification deletion
- **Auto-refresh:** Counts update every 30 seconds

## 🔧 Advanced Features

### Detailed Notifications with Context
```php
// For grades with teacher comments
triggerDetailedMarksNotification(
    $student_id, 
    'English', 
    92, 
    100, 
    'Mid-term Exam', 
    'Excellent analysis of themes. Shows deep understanding.'
);

// For attendance with additional details
triggerDetailedAttendanceNotification(
    $student_id, 
    'late', 
    '2024-01-20', 
    '2',           // Period
    'Mathematics', // Subject
    'Arrived 10 minutes late due to traffic'
);
```

### Bulk Operations
```php
// For entire class attendance
$student_ids = [1, 2, 3, 4, 5];
triggerBulkAttendanceNotification($student_ids, 'present', '2024-01-20', 'Mathematics');
```

## 📊 Testing Your Integration

1. **Run the test script:** Visit `/test_all_notifications.php`
2. **Check notification counts:** Look for red badges on bell icons
3. **Test notifications:** Click bell icons to view notifications
4. **Verify read/unread:** Check that notifications change status when clicked

## 🚀 Implementation Checklist

- [ ] Include `notification_triggers.php` in your files
- [ ] Add `triggerMarksNotification()` after grade insertion
- [ ] Add `triggerAttendanceNotification()` after attendance recording
- [ ] Add `triggerStudentUpdateNotification()` after student updates
- [ ] Add `triggerStudentDeleteNotification()` before student deletion
- [ ] Test with sample data
- [ ] Verify notification counts appear on bell icons
- [ ] Test read/unread functionality

## 🎯 Result

After integration, parents will automatically receive notifications for:
- ✅ New grades/marks posted
- ✅ Attendance updates (present/absent/late)
- ✅ Student information changes
- ✅ Student status updates
- ✅ Real-time notification counts on bell icon
- ✅ Read/unread status tracking

The notification system is now fully integrated and will automatically notify parents whenever student marks are added, attendance is collected, or student information is updated! 🎉
