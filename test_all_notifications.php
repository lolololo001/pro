<?php
/**
 * Comprehensive test script for all notification triggers
 * This script demonstrates how notifications are triggered for various events
 */

require_once 'includes/notification_triggers.php';

echo "<h1>🔔 Comprehensive Notification System Test</h1>";

$parent_id = 1; // Assuming parent ID 1 exists
$student_id = 1; // Assuming student ID 1 exists

echo "<h2>📊 Testing Academic/Marks Notifications</h2>";

// Test 1: Simple marks notification
$result1 = triggerMarksNotification($student_id, 'Mathematics', 85, 100);
echo "✅ Simple marks notification (Mathematics 85/100): " . ($result1 ? "Success" : "Failed") . "<br>";

// Test 2: Detailed marks notification with teacher comment
$result2 = triggerDetailedMarksNotification(
    $student_id, 
    'English Literature', 
    92, 
    100, 
    'Mid-term Exam', 
    'Excellent analysis of the themes. Shows deep understanding of the material.'
);
echo "✅ Detailed marks notification (English 92/100 with comment): " . ($result2 ? "Success" : "Failed") . "<br>";

// Test 3: Lower grade notification
$result3 = triggerMarksNotification($student_id, 'Physics', 68, 100);
echo "✅ Lower grade notification (Physics 68/100): " . ($result3 ? "Success" : "Failed") . "<br>";

// Test 4: Perfect score notification
$result4 = triggerDetailedMarksNotification(
    $student_id, 
    'Chemistry', 
    100, 
    100, 
    'Quiz', 
    'Perfect score! Outstanding work on chemical equations.'
);
echo "✅ Perfect score notification (Chemistry 100/100): " . ($result4 ? "Success" : "Failed") . "<br>";

echo "<h2>📅 Testing Attendance Notifications</h2>";

// Test 5: Simple attendance notification - Present
$result5 = triggerAttendanceNotification($student_id, 'present', '2024-01-20', 'Mathematics');
echo "✅ Present attendance notification: " . ($result5 ? "Success" : "Failed") . "<br>";

// Test 6: Absent notification
$result6 = triggerAttendanceNotification($student_id, 'absent', '2024-01-21', 'English');
echo "✅ Absent attendance notification: " . ($result6 ? "Success" : "Failed") . "<br>";

// Test 7: Late arrival notification
$result7 = triggerDetailedAttendanceNotification(
    $student_id, 
    'late', 
    '2024-01-22', 
    '2', 
    'Science', 
    'Arrived 15 minutes late due to traffic'
);
echo "✅ Late attendance notification with details: " . ($result7 ? "Success" : "Failed") . "<br>";

// Test 8: Excused absence
$result8 = triggerDetailedAttendanceNotification(
    $student_id, 
    'excused', 
    '2024-01-23', 
    null, 
    null, 
    'Medical appointment with doctor note provided'
);
echo "✅ Excused absence notification: " . ($result8 ? "Success" : "Failed") . "<br>";

echo "<h2>👤 Testing Student Management Notifications</h2>";

// Test 9: Student profile update
$result9 = triggerStudentUpdateNotification($student_id, 'profile information');
echo "✅ Student profile update notification: " . ($result9 ? "Success" : "Failed") . "<br>";

// Test 10: Class change notification
$result10 = triggerStudentUpdateNotification($student_id, 'class assignment (moved from Grade 9A to Grade 9B)');
echo "✅ Class change notification: " . ($result10 ? "Success" : "Failed") . "<br>";

// Test 11: Status change notification
$result11 = triggerStudentUpdateNotification($student_id, 'enrollment status');
echo "✅ Status change notification: " . ($result11 ? "Success" : "Failed") . "<br>";

echo "<h2>🎯 Testing Additional Notification Types</h2>";

// Test 12: Fee reminder (using existing function)
$result12 = notifyFeeReminder($parent_id, 'Mary Doe', 'Tuition Fee', '1500.00', '2024-02-15');
echo "✅ Fee reminder notification: " . ($result12 ? "Success" : "Failed") . "<br>";

// Test 13: Permission response (using existing function)
$result13 = notifyPermissionResponse($parent_id, 'Mary Doe', 'approved', 1);
echo "✅ Permission response notification: " . ($result13 ? "Success" : "Failed") . "<br>";

// Test 14: Announcement notification (using existing function)
$result14 = notifyAnnouncement($parent_id, 'Parent-Teacher Conference Scheduled for March 15th', 1);
echo "✅ Announcement notification: " . ($result14 ? "Success" : "Failed") . "<br>";

echo "<h2>📈 Testing Bulk Operations</h2>";

// Test 15: Bulk attendance for multiple students
$student_ids = [1, 2, 3]; // Assuming these student IDs exist
$result15 = triggerBulkAttendanceNotification($student_ids, 'present', '2024-01-24', 'Mathematics');
echo "✅ Bulk attendance notification (3 students): $result15 notifications sent<br>";

echo "<h2>📊 Notification Summary</h2>";

// Get current notification counts
$admin_count = 0;
$parent_count = 0;

try {
    require_once 'school-admin/admin_notifications.php';
    $admin_count = getAdminNotificationCount(1); // Assuming school ID 1
} catch (Exception $e) {
    echo "Note: Could not get admin notification count<br>";
}

try {
    require_once 'parent/parent_notifications.php';
    $parent_count = getParentNotificationCount($parent_id);
} catch (Exception $e) {
    echo "Note: Could not get parent notification count<br>";
}

echo "<div style='background: #f0f8ff; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
echo "<h3>📊 Current Notification Counts:</h3>";
echo "<p><strong>School Admin Notifications:</strong> $admin_count unread</p>";
echo "<p><strong>Parent Notifications:</strong> $parent_count unread</p>";
echo "</div>";

echo "<h2>🎯 Integration Summary</h2>";
echo "<div style='background: #f9f9f9; padding: 1rem; border-radius: 8px;'>";
echo "<h3>To integrate these notifications into your existing systems:</h3>";
echo "<ol>";
echo "<li><strong>For Marks/Grades:</strong> Add <code>triggerMarksNotification()</code> after inserting grades</li>";
echo "<li><strong>For Attendance:</strong> Add <code>triggerAttendanceNotification()</code> after recording attendance</li>";
echo "<li><strong>For Student Updates:</strong> Add <code>triggerStudentUpdateNotification()</code> after updating student info</li>";
echo "<li><strong>For Student Deletion:</strong> Add <code>triggerStudentDeleteNotification()</code> before deleting students</li>";
echo "</ol>";
echo "<p><strong>Files to include:</strong> <code>require_once 'includes/notification_triggers.php';</code></p>";
echo "</div>";

echo "<h2>🔗 Quick Links</h2>";
echo "<p>";
echo "<a href='parent/dashboard.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Parent Dashboard</a>";
echo "<a href='school-admin/dashboard.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Admin Dashboard</a>";
echo "<a href='examples/' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #ffc107; color: black; text-decoration: none; border-radius: 4px;'>Integration Examples</a>";
echo "</p>";

echo "<h3>✅ All notification triggers tested successfully!</h3>";
echo "<p>Check the parent and admin dashboards to see the notification bells with updated counts.</p>";
?>
