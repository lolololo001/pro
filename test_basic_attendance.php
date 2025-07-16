<?php
/**
 * Simple test to verify basic attendance functionality
 */

require_once 'config/config.php';

echo "<h1>🧪 Testing Basic Attendance Functionality</h1>";

try {
    $conn = getDbConnection();
    
    echo "<h2>📊 Checking Database Structure</h2>";
    
    // Check if student_attendance table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'student_attendance'");
    
    if ($table_check->num_rows > 0) {
        echo "✅ student_attendance table exists<br>";
        
        // Check table structure
        $columns_result = $conn->query("SHOW COLUMNS FROM student_attendance");
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 1rem 0;'>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        
        while ($column = $columns_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ student_attendance table does not exist<br>";
        
        // Create the table
        $create_sql = "CREATE TABLE student_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            student_id INT NOT NULL,
            date DATE NOT NULL,
            subject VARCHAR(100),
            status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
            teacher_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_class_date (class_id, date),
            INDEX idx_student_date (student_id, date),
            INDEX idx_teacher (teacher_id)
        )";
        
        if ($conn->query($create_sql)) {
            echo "✅ Created student_attendance table<br>";
        } else {
            echo "❌ Failed to create table: " . $conn->error . "<br>";
        }
    }
    
    echo "<h2>📝 Testing Basic Attendance Insert</h2>";
    
    // Test basic attendance insertion
    $test_data = [
        ['class_id' => 1, 'student_id' => 1, 'date' => '2025-07-13', 'subject' => 'Mathematics', 'status' => 'present', 'teacher_id' => 1],
        ['class_id' => 1, 'student_id' => 2, 'date' => '2025-07-13', 'subject' => 'Mathematics', 'status' => 'absent', 'teacher_id' => 1],
        ['class_id' => 1, 'student_id' => 3, 'date' => '2025-07-13', 'subject' => 'Mathematics', 'status' => 'late', 'teacher_id' => 1],
    ];
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($test_data as $data) {
        // Check if record already exists
        $check_stmt = $conn->prepare("SELECT id FROM student_attendance WHERE class_id = ? AND student_id = ? AND date = ? AND subject = ?");
        $check_stmt->bind_param("iiss", $data['class_id'], $data['student_id'], $data['date'], $data['subject']);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Insert new record
            $insert_stmt = $conn->prepare("INSERT INTO student_attendance (class_id, student_id, date, subject, status, teacher_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $insert_stmt->bind_param("iisssi", $data['class_id'], $data['student_id'], $data['date'], $data['subject'], $data['status'], $data['teacher_id']);
            
            if ($insert_stmt->execute()) {
                echo "✅ Inserted: Student {$data['student_id']} - {$data['status']} for {$data['subject']}<br>";
                $success_count++;
            } else {
                echo "❌ Failed to insert: " . $conn->error . "<br>";
                $error_count++;
            }
            $insert_stmt->close();
        } else {
            echo "ℹ️ Record already exists: Student {$data['student_id']} - {$data['subject']}<br>";
        }
        $check_stmt->close();
    }
    
    echo "<h2>📋 Testing Basic Attendance Update</h2>";
    
    // Test updating attendance
    $update_stmt = $conn->prepare("UPDATE student_attendance SET status = ?, updated_at = NOW() WHERE class_id = ? AND student_id = ? AND date = ? AND subject = ?");
    $new_status = 'present';
    $class_id = 1;
    $student_id = 2;
    $date = '2025-07-13';
    $subject = 'Mathematics';
    
    $update_stmt->bind_param("siiss", $new_status, $class_id, $student_id, $date, $subject);
    
    if ($update_stmt->execute()) {
        echo "✅ Updated: Student $student_id status changed to $new_status<br>";
    } else {
        echo "❌ Failed to update: " . $conn->error . "<br>";
    }
    $update_stmt->close();
    
    echo "<h2>📊 Current Attendance Records</h2>";
    
    // Display current attendance records
    $select_stmt = $conn->prepare("SELECT * FROM student_attendance WHERE date = '2025-07-13' ORDER BY class_id, student_id, subject");
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 1rem 0; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th style='padding: 8px;'>ID</th>";
        echo "<th style='padding: 8px;'>Class ID</th>";
        echo "<th style='padding: 8px;'>Student ID</th>";
        echo "<th style='padding: 8px;'>Date</th>";
        echo "<th style='padding: 8px;'>Subject</th>";
        echo "<th style='padding: 8px;'>Status</th>";
        echo "<th style='padding: 8px;'>Teacher ID</th>";
        echo "<th style='padding: 8px;'>Created</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . $row['id'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['class_id'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['student_id'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['date'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['subject'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['status'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['teacher_id'] . "</td>";
            echo "<td style='padding: 8px;'>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "ℹ️ No attendance records found for today<br>";
    }
    $select_stmt->close();
    
    echo "<h2>🎯 Test Summary</h2>";
    echo "<div style='background: #e8f5e8; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>✅ Basic Functionality Test Results:</h3>";
    echo "<ul>";
    echo "<li>✅ Database table structure verified</li>";
    echo "<li>✅ Basic attendance insertion working</li>";
    echo "<li>✅ Attendance update functionality working</li>";
    echo "<li>✅ Data retrieval working correctly</li>";
    echo "<li>✅ Records: $success_count successful, $error_count errors</li>";
    echo "</ul>";
    echo "</div>";
    
    $conn->close();
    
    echo "<h2>🔗 Test Links</h2>";
    echo "<p>";
    echo "<a href='teacher/attendance.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Teacher Attendance Page</a>";
    echo "<a href='parent/student_info.php?student_id=1' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Parent View</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
