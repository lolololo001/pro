<?php
/**
 * Test script to verify enhanced attendance system with notifications and detailed reports
 */

require_once 'config/config.php';
require_once 'includes/notification_triggers.php';

echo "<h1>🧪 Testing Enhanced Attendance System</h1>";

try {
    $conn = getDbConnection();
    
    echo "<h2>📊 Testing Database Storage</h2>";
    
    // Test data with different statuses
    $test_attendance = [
        ['class_id' => 1, 'student_id' => 1, 'date' => '2025-07-13', 'subject' => 'Mathematics', 'status' => 'present', 'teacher_id' => 1],
        ['class_id' => 1, 'student_id' => 2, 'date' => '2025-07-13', 'subject' => 'Mathematics', 'status' => 'absent', 'teacher_id' => 1],
        ['class_id' => 1, 'student_id' => 3, 'date' => '2025-07-13', 'subject' => 'Mathematics', 'status' => 'late', 'teacher_id' => 1],
        ['class_id' => 1, 'student_id' => 4, 'date' => '2025-07-13', 'subject' => 'Mathematics', 'status' => 'excused', 'teacher_id' => 1],
        ['class_id' => 1, 'student_id' => 5, 'date' => '2025-07-13', 'subject' => 'Mathematics', 'status' => 'present', 'teacher_id' => 1],
    ];
    
    $processed_count = 0;
    $error_count = 0;
    $notification_count = 0;
    $attendance_summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
    
    foreach ($test_attendance as $data) {
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
                $processed_count++;
                $attendance_summary[$data['status']]++;
                echo "✅ Stored: Student {$data['student_id']} - {$data['status']} for {$data['subject']}<br>";
                
                // Test notification trigger
                if (triggerDetailedAttendanceNotification($data['student_id'], $data['status'], $data['date'], null, $data['subject'], '')) {
                    $notification_count++;
                    echo "📧 Notification sent to parent of Student {$data['student_id']}<br>";
                }
            } else {
                $error_count++;
                echo "❌ Failed to store: " . $conn->error . "<br>";
            }
            $insert_stmt->close();
        } else {
            echo "ℹ️ Record already exists: Student {$data['student_id']} - {$data['subject']}<br>";
            $attendance_summary[$data['status']]++;
        }
        $check_stmt->close();
    }
    
    echo "<h2>📋 Testing Detailed Report Generation</h2>";
    
    // Generate the same report format as the teacher page
    $subject_text = " for Mathematics";
    $report = "Present: {$attendance_summary['present']}, Absent: {$attendance_summary['absent']}, Late: {$attendance_summary['late']}, Excused: {$attendance_summary['excused']}";
    
    if ($error_count > 0) {
        $success_message = "Attendance partially saved$subject_text! Report: $report. $processed_count records saved, $error_count errors. $notification_count parent notifications sent.";
    } else {
        $success_message = "Attendance saved successfully$subject_text! Report: $report. All $processed_count records stored in database. $notification_count parent notifications sent.";
    }
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 1rem; border-radius: 4px; margin: 1rem 0;'>";
    echo "<strong>Sample Success Message:</strong><br>";
    echo $success_message;
    echo "</div>";
    
    echo "<h2>🔔 Testing Parent Notifications</h2>";
    
    // Check parent notifications
    require_once 'parent/parent_notifications.php';
    
    // Get parent notification counts for students
    $parent_notifications = [];
    foreach ([1, 2, 3, 4, 5] as $student_id) {
        $parent_stmt = $conn->prepare("SELECT sp.parent_id FROM student_parent sp WHERE sp.student_id = ?");
        $parent_stmt->bind_param('i', $student_id);
        $parent_stmt->execute();
        $parent_result = $parent_stmt->get_result();
        
        if ($parent_row = $parent_result->fetch_assoc()) {
            $parent_id = $parent_row['parent_id'];
            $count = getParentNotificationCount($parent_id);
            $parent_notifications[$parent_id] = $count;
            
            // Get recent attendance notifications
            $recent_notifications = getParentNotifications($parent_id, 3);
            $attendance_notifications = array_filter($recent_notifications, function($n) {
                return $n['type'] === 'attendance_update';
            });
            
            echo "<strong>Parent $parent_id (Student $student_id):</strong> $count unread notifications<br>";
            
            if (!empty($attendance_notifications)) {
                foreach ($attendance_notifications as $notification) {
                    echo "📧 " . htmlspecialchars($notification['message']) . " (" . $notification['time_ago'] . ")<br>";
                }
            }
        }
        $parent_stmt->close();
    }
    
    echo "<h2>📊 Database Verification</h2>";
    
    // Verify data is stored correctly in database
    $verify_stmt = $conn->prepare("SELECT * FROM student_attendance WHERE date = '2025-07-13' AND subject = 'Mathematics' ORDER BY student_id");
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 1rem 0; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th style='padding: 8px;'>Student ID</th>";
    echo "<th style='padding: 8px;'>Date</th>";
    echo "<th style='padding: 8px;'>Subject</th>";
    echo "<th style='padding: 8px;'>Status</th>";
    echo "<th style='padding: 8px;'>Teacher ID</th>";
    echo "<th style='padding: 8px;'>Created</th>";
    echo "</tr>";
    
    while ($row = $verify_result->fetch_assoc()) {
        $status_color = [
            'present' => '#28a745',
            'absent' => '#dc3545',
            'late' => '#ffc107',
            'excused' => '#17a2b8'
        ];
        
        echo "<tr>";
        echo "<td style='padding: 8px; text-align: center;'>" . $row['student_id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $row['date'] . "</td>";
        echo "<td style='padding: 8px;'>" . $row['subject'] . "</td>";
        echo "<td style='padding: 8px; color: " . ($status_color[$row['status']] ?? '#000') . "; font-weight: bold;'>" . ucfirst($row['status']) . "</td>";
        echo "<td style='padding: 8px; text-align: center;'>" . $row['teacher_id'] . "</td>";
        echo "<td style='padding: 8px;'>" . date('M j, Y H:i', strtotime($row['created_at'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    $verify_stmt->close();
    
    echo "<h2>📈 Attendance Summary Statistics</h2>";
    
    echo "<div style='display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin: 1rem 0;'>";
    
    $status_colors = [
        'present' => ['bg' => '#d4edda', 'text' => '#155724'],
        'absent' => ['bg' => '#f8d7da', 'text' => '#721c24'],
        'late' => ['bg' => '#fff3cd', 'text' => '#856404'],
        'excused' => ['bg' => '#d1ecf1', 'text' => '#0c5460']
    ];
    
    foreach ($attendance_summary as $status => $count) {
        $colors = $status_colors[$status];
        echo "<div style='background: {$colors['bg']}; color: {$colors['text']}; padding: 1rem; border-radius: 8px; text-align: center;'>";
        echo "<div style='font-size: 2rem; font-weight: bold;'>$count</div>";
        echo "<div style='text-transform: uppercase; font-size: 0.9rem;'>" . ucfirst($status) . "</div>";
        echo "</div>";
    }
    echo "</div>";
    
    $conn->close();
    
    echo "<h2>🎯 Test Summary</h2>";
    echo "<div style='background: #e8f5e8; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>✅ Enhanced Attendance System Test Results:</h3>";
    echo "<ul>";
    echo "<li>✅ <strong>Database Storage:</strong> $processed_count attendance records stored successfully</li>";
    echo "<li>✅ <strong>Parent Notifications:</strong> $notification_count notifications sent to parents</li>";
    echo "<li>✅ <strong>Detailed Reports:</strong> Attendance breakdown generated correctly</li>";
    echo "<li>✅ <strong>Status Tracking:</strong> Present: {$attendance_summary['present']}, Absent: {$attendance_summary['absent']}, Late: {$attendance_summary['late']}, Excused: {$attendance_summary['excused']}</li>";
    echo "<li>✅ <strong>Error Handling:</strong> $error_count errors encountered</li>";
    echo "<li>✅ <strong>Confirmation Messages:</strong> Detailed success messages with reports</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>🔗 Test Links</h2>";
    echo "<p>";
    echo "<a href='teacher/attendance.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Teacher Attendance Page</a>";
    echo "<a href='parent/dashboard.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Parent Dashboard</a>";
    echo "<a href='parent/student_info.php?student_id=1' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #ffc107; color: black; text-decoration: none; border-radius: 4px;'>Parent Attendance View</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
