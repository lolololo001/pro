<?php
/**
 * Test script to create sample admin notifications
 */

require_once 'admin_notifications.php';

echo "<h2>Testing Admin Notification System</h2>";

$school_id = 1; // Assuming school ID 1 exists

// Create permission request notification
$result1 = createAdminNotification(
    $school_id,
    "New Permission Request",
    "John Doe has submitted a leave request for his child Mary Doe",
    'permission_request',
    'permission_requests',
    1
);
echo "Permission request notification: " . ($result1 ? "✅ Created" : "❌ Failed") . "<br>";

// Create feedback notification
$result2 = createAdminNotification(
    $school_id,
    "New Parent Feedback",
    "Jane Smith submitted feedback about school facilities",
    'feedback',
    'parent_feedback',
    1
);
echo "Feedback notification: " . ($result2 ? "✅ Created" : "❌ Failed") . "<br>";

// Create student registration notification
$result3 = createAdminNotification(
    $school_id,
    "New Student Registration",
    "Alex Johnson has been registered by parent Mike Johnson",
    'student_registration',
    'students',
    1
);
echo "Student registration notification: " . ($result3 ? "✅ Created" : "❌ Failed") . "<br>";

// Create general notification
$result4 = createAdminNotification(
    $school_id,
    "System Update",
    "The school management system has been updated with new features",
    'general',
    null,
    null
);
echo "General notification: " . ($result4 ? "✅ Created" : "❌ Failed") . "<br>";

// Test getting notification count
$count = getAdminNotificationCount($school_id);
echo "<br><strong>Total unread notifications: $count</strong><br>";

// Test getting notifications
$notifications = getAdminNotifications($school_id, 5);
echo "<strong>Notifications retrieved: " . count($notifications) . "</strong><br>";

// Display sample notifications
if (!empty($notifications)) {
    echo "<h4>Sample Notifications:</h4>";
    foreach ($notifications as $notification) {
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px 0; border-radius: 5px; background: " . ($notification['is_read'] ? '#f9f9f9' : '#fff3cd') . ";'>";
        echo "<strong>" . htmlspecialchars($notification['title']) . "</strong><br>";
        echo htmlspecialchars($notification['message']) . "<br>";
        echo "<small>Type: " . $notification['type'] . " | " . $notification['time_ago'] . " | Status: " . ($notification['is_read'] ? 'Read' : 'Unread') . "</small>";
        echo "</div>";
    }
}

echo "<h3>✅ Admin Notification System Test Complete!</h3>";
echo "<p><a href='dashboard.php'>Go to Admin Dashboard</a></p>";
?>
