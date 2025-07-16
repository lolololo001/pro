<?php
/**
 * Test script to create sample parent notifications
 */

require_once 'parent_notifications.php';

echo "<h2>Testing Parent Notification System</h2>";

$parent_id = 1; // Assuming parent ID 1 exists

// Create permission response notification
$result1 = notifyPermissionResponse(
    $parent_id,
    "Mary Doe",
    "approved",
    1
);
echo "Permission response notification: " . ($result1 ? "✅ Created" : "❌ Failed") . "<br>";

// Create academic update notification
$result2 = notifyAcademicUpdate(
    $parent_id,
    "Mary Doe",
    "Mathematics",
    "85/100",
    1
);
echo "Academic update notification: " . ($result2 ? "✅ Created" : "❌ Failed") . "<br>";

// Create another academic update
$result3 = notifyAcademicUpdate(
    $parent_id,
    "Mary Doe",
    "English",
    "92/100",
    2
);
echo "English grade notification: " . ($result3 ? "✅ Created" : "❌ Failed") . "<br>";

// Create attendance update notification
$result4 = notifyAttendanceUpdate(
    $parent_id,
    "Mary Doe",
    "present",
    "2024-01-20"
);
echo "Attendance update notification: " . ($result4 ? "✅ Created" : "❌ Failed") . "<br>";

// Create fee reminder notification
$result5 = notifyFeeReminder(
    $parent_id,
    "Mary Doe",
    "Tuition Fee",
    "1500.00",
    "2024-02-15"
);
echo "Fee reminder notification: " . ($result5 ? "✅ Created" : "❌ Failed") . "<br>";

// Create announcement notification
$result6 = notifyAnnouncement(
    $parent_id,
    "Parent-Teacher Conference Scheduled",
    1
);
echo "Announcement notification: " . ($result6 ? "✅ Created" : "❌ Failed") . "<br>";

// Create general notification
$result7 = createParentNotification(
    $parent_id,
    "Welcome to Parent Portal",
    "Welcome to the new parent portal! You can now track your child's progress in real-time.",
    'general',
    null,
    null
);
echo "General notification: " . ($result7 ? "✅ Created" : "❌ Failed") . "<br>";

// Test getting notification count
$count = getParentNotificationCount($parent_id);
echo "<br><strong>Total unread notifications: $count</strong><br>";

// Test getting notifications
$notifications = getParentNotifications($parent_id, 10);
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

echo "<h3>✅ Parent Notification System Test Complete!</h3>";
echo "<p><a href='dashboard.php'>Go to Parent Dashboard</a></p>";
?>
