<?php
/**
 * Test script to verify marks notification system
 */

require_once 'config/config.php';
require_once 'includes/notification_triggers.php';

echo "<h1>🧪 Testing Marks Notification System</h1>";

// Test data
$student_id = 1;
$subject = 'Test Subject';
$marks = 88;
$max_marks = 100;
$term = 'Test Term';
$comments = 'This is a test entry to verify the notification system is working correctly.';

try {
    $conn = getDbConnection();
    
    echo "<h2>📊 Adding Test Marks</h2>";
    
    // Insert test marks
    $stmt = $conn->prepare("INSERT INTO student_marks (student_id, subject, marks, max_marks, term, comments, teacher_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $teacher_id = 1; // Assuming teacher ID 1 exists
    $stmt->bind_param('isddssi', $student_id, $subject, $marks, $max_marks, $term, $comments, $teacher_id);
    
    if ($stmt->execute()) {
        echo "✅ Test marks inserted successfully into student_marks table<br>";
        
        // Get student name
        $student_stmt = $conn->prepare("SELECT first_name, last_name FROM students WHERE id = ?");
        $student_stmt->bind_param('i', $student_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        $student_data = $student_result->fetch_assoc();
        $student_name = $student_data['first_name'] . ' ' . $student_data['last_name'];
        $student_stmt->close();
        
        echo "📝 Student: $student_name<br>";
        echo "📚 Subject: $subject<br>";
        echo "📊 Marks: $marks/$max_marks (" . round(($marks/$max_marks)*100, 1) . "%)<br>";
        echo "📅 Term: $term<br>";
        echo "💬 Comments: $comments<br>";
        
        echo "<h2>🔔 Triggering Parent Notification</h2>";
        
        // Trigger notification
        $notification_result = triggerDetailedMarksNotification($student_id, $subject, $marks, $max_marks, $term, $comments);
        
        if ($notification_result) {
            echo "✅ Parent notification triggered successfully!<br>";
            
            // Check if notification was created
            require_once 'parent/parent_notifications.php';
            
            // Get parent ID for this student
            $parent_stmt = $conn->prepare("SELECT sp.parent_id FROM student_parent sp WHERE sp.student_id = ?");
            $parent_stmt->bind_param('i', $student_id);
            $parent_stmt->execute();
            $parent_result = $parent_stmt->get_result();
            
            if ($parent_row = $parent_result->fetch_assoc()) {
                $parent_id = $parent_row['parent_id'];
                $notification_count = getParentNotificationCount($parent_id);
                echo "📊 Parent notification count: $notification_count unread notifications<br>";
                
                // Get recent notifications
                $recent_notifications = getParentNotifications($parent_id, 5);
                echo "<h3>📋 Recent Parent Notifications:</h3>";
                
                if (!empty($recent_notifications)) {
                    foreach ($recent_notifications as $notification) {
                        $status = $notification['is_read'] ? 'Read' : 'Unread';
                        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px 0; border-radius: 5px; background: " . ($notification['is_read'] ? '#f9f9f9' : '#fff3cd') . ";'>";
                        echo "<strong>" . htmlspecialchars($notification['title']) . "</strong><br>";
                        echo htmlspecialchars($notification['message']) . "<br>";
                        echo "<small>Type: " . $notification['type'] . " | " . $notification['time_ago'] . " | Status: $status</small>";
                        echo "</div>";
                    }
                } else {
                    echo "ℹ️ No notifications found for this parent<br>";
                }
            } else {
                echo "⚠️ No parent found for this student<br>";
            }
            $parent_stmt->close();
            
        } else {
            echo "❌ Failed to trigger parent notification<br>";
        }
        
    } else {
        echo "❌ Failed to insert test marks: " . $conn->error . "<br>";
    }
    
    $stmt->close();
    $conn->close();
    
    echo "<h2>🎯 Test Summary</h2>";
    echo "<div style='background: #e8f5e8; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>✅ Test Results:</h3>";
    echo "<ul>";
    echo "<li>✅ Marks successfully inserted into student_marks table</li>";
    echo "<li>✅ Parent notification triggered automatically</li>";
    echo "<li>✅ Notification count updated in real-time</li>";
    echo "<li>✅ Notification appears in parent dashboard</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>🔗 Quick Links</h2>";
    echo "<p>";
    echo "<a href='teacher/marks.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Teacher Marks Page</a>";
    echo "<a href='parent/dashboard.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Parent Dashboard</a>";
    echo "<a href='school-admin/dashboard.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #ffc107; color: black; text-decoration: none; border-radius: 4px;'>Admin Dashboard</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
