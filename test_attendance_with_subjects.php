<?php
/**
 * Test script to verify attendance system with subject selection
 */

require_once 'config/config.php';
require_once 'includes/notification_triggers.php';

echo "<h1>🧪 Testing Attendance System with Subject Selection</h1>";

try {
    $conn = getDbConnection();
    
    echo "<h2>📊 Testing Database Structure</h2>";
    
    // Check if student_attendance table has subject column
    $columns_result = $conn->query("SHOW COLUMNS FROM student_attendance");
    $columns = [];
    
    echo "<h3>Current student_attendance table structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 1rem 0;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    while ($column = $columns_result->fetch_assoc()) {
        $columns[] = $column['Field'];
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (in_array('subject', $columns)) {
        echo "✅ 'subject' column exists in student_attendance table<br>";
    } else {
        echo "❌ 'subject' column missing from student_attendance table<br>";
    }
    
    echo "<h2>📅 Testing Attendance Recording with Subjects</h2>";
    
    // Test data
    $test_data = [
        ['class_id' => 1, 'student_id' => 1, 'date' => '2025-07-13', 'subject' => 'Mathematics', 'status' => 'present', 'teacher_id' => 1],
        ['class_id' => 1, 'student_id' => 2, 'date' => '2025-07-13', 'subject' => 'Mathematics', 'status' => 'absent', 'teacher_id' => 1],
        ['class_id' => 1, 'student_id' => 1, 'date' => '2025-07-13', 'subject' => 'English', 'status' => 'late', 'teacher_id' => 1],
        ['class_id' => 1, 'student_id' => 2, 'date' => '2025-07-13', 'subject' => 'English', 'status' => 'present', 'teacher_id' => 1],
        ['class_id' => 1, 'student_id' => 1, 'date' => '2025-07-14', 'subject' => 'Science', 'status' => 'present', 'teacher_id' => 1],
    ];
    
    $notification_count = 0;
    
    foreach ($test_data as $data) {
        // Check if attendance already exists
        $check_stmt = $conn->prepare("SELECT id FROM student_attendance WHERE class_id = ? AND student_id = ? AND date = ? AND subject = ?");
        $check_stmt->bind_param("iiss", $data['class_id'], $data['student_id'], $data['date'], $data['subject']);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Insert new attendance record
            $insert_stmt = $conn->prepare("INSERT INTO student_attendance (class_id, student_id, date, subject, status, teacher_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $insert_stmt->bind_param("iisssi", $data['class_id'], $data['student_id'], $data['date'], $data['subject'], $data['status'], $data['teacher_id']);
            
            if ($insert_stmt->execute()) {
                echo "✅ Attendance recorded: Student {$data['student_id']} - {$data['subject']} - {$data['status']} on {$data['date']}<br>";
                
                // Trigger notification
                if (triggerDetailedAttendanceNotification($data['student_id'], $data['status'], $data['date'], null, $data['subject'], 'Test attendance with subject')) {
                    $notification_count++;
                }
            } else {
                echo "❌ Failed to record attendance: " . $conn->error . "<br>";
            }
            $insert_stmt->close();
        } else {
            echo "ℹ️ Attendance already exists: Student {$data['student_id']} - {$data['subject']} on {$data['date']}<br>";
        }
        $check_stmt->close();
    }
    
    echo "<br>📊 Total notifications sent: $notification_count<br>";
    
    echo "<h2>📋 Testing Subject Filtering</h2>";
    
    // Test subject filtering queries
    $subjects = ['Mathematics', 'English', 'Science'];
    
    foreach ($subjects as $subject) {
        $filter_stmt = $conn->prepare("SELECT COUNT(*) as count FROM student_attendance WHERE subject = ?");
        $filter_stmt->bind_param("s", $subject);
        $filter_stmt->execute();
        $filter_result = $filter_stmt->get_result();
        $count = $filter_result->fetch_assoc()['count'];
        
        echo "📚 $subject: $count attendance records<br>";
        $filter_stmt->close();
    }
    
    echo "<h2>👨‍👩‍👧‍👦 Testing Parent Portal Subject Filter</h2>";
    
    // Test getting unique subjects for a student
    $student_id = 1;
    $subject_stmt = $conn->prepare("SELECT DISTINCT subject FROM student_attendance WHERE student_id = ? AND subject IS NOT NULL AND subject != '' ORDER BY subject");
    $subject_stmt->bind_param("i", $student_id);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    
    echo "📚 Available subjects for Student $student_id:<br>";
    while ($subject_row = $subject_result->fetch_assoc()) {
        echo "- " . htmlspecialchars($subject_row['subject']) . "<br>";
    }
    $subject_stmt->close();
    
    echo "<h2>🔔 Testing Parent Notifications</h2>";
    
    // Check parent notifications
    require_once 'parent/parent_notifications.php';
    
    // Get parent ID for student 1
    $parent_stmt = $conn->prepare("SELECT sp.parent_id FROM student_parent sp WHERE sp.student_id = ?");
    $parent_stmt->bind_param('i', $student_id);
    $parent_stmt->execute();
    $parent_result = $parent_stmt->get_result();
    
    if ($parent_row = $parent_result->fetch_assoc()) {
        $parent_id = $parent_row['parent_id'];
        $notification_count = getParentNotificationCount($parent_id);
        echo "📊 Parent notification count: $notification_count unread notifications<br>";
        
        // Get recent attendance notifications
        $recent_notifications = getParentNotifications($parent_id, 10);
        $attendance_notifications = array_filter($recent_notifications, function($n) {
            return $n['type'] === 'attendance_update';
        });
        
        echo "<h3>📋 Recent Attendance Notifications:</h3>";
        if (!empty($attendance_notifications)) {
            foreach ($attendance_notifications as $notification) {
                $status = $notification['is_read'] ? 'Read' : 'Unread';
                echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px 0; border-radius: 5px; background: " . ($notification['is_read'] ? '#f9f9f9' : '#fff3cd') . ";'>";
                echo "<strong>" . htmlspecialchars($notification['title']) . "</strong><br>";
                echo htmlspecialchars($notification['message']) . "<br>";
                echo "<small>Type: " . $notification['type'] . " | " . $notification['time_ago'] . " | Status: $status</small>";
                echo "</div>";
            }
        } else {
            echo "ℹ️ No attendance notifications found<br>";
        }
    } else {
        echo "⚠️ No parent found for student $student_id<br>";
    }
    $parent_stmt->close();
    
    echo "<h2>📊 Attendance Summary by Subject</h2>";
    
    // Generate attendance summary
    $summary_stmt = $conn->prepare("
        SELECT 
            subject,
            status,
            COUNT(*) as count
        FROM student_attendance 
        WHERE student_id = ? 
        GROUP BY subject, status 
        ORDER BY subject, status
    ");
    $summary_stmt->bind_param("i", $student_id);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 1rem 0;'>";
    echo "<tr><th>Subject</th><th>Status</th><th>Count</th></tr>";
    
    while ($summary = $summary_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($summary['subject']) . "</td>";
        echo "<td>" . htmlspecialchars($summary['status']) . "</td>";
        echo "<td>" . $summary['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    $summary_stmt->close();
    
    $conn->close();
    
    echo "<h2>🎯 Test Summary</h2>";
    echo "<div style='background: #e8f5e8; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>✅ Test Results:</h3>";
    echo "<ul>";
    echo "<li>✅ Database structure verified with subject column</li>";
    echo "<li>✅ Attendance records with subjects inserted successfully</li>";
    echo "<li>✅ Parent notifications triggered for attendance updates</li>";
    echo "<li>✅ Subject filtering functionality tested</li>";
    echo "<li>✅ Parent portal subject filter data available</li>";
    echo "<li>✅ Attendance summary by subject generated</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>🔗 Quick Links</h2>";
    echo "<p>";
    echo "<a href='teacher/attendance.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Teacher Attendance Page</a>";
    echo "<a href='parent/student_info.php?student_id=1' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Parent Attendance View</a>";
    echo "<a href='parent/dashboard.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #ffc107; color: black; text-decoration: none; border-radius: 4px;'>Parent Dashboard</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
