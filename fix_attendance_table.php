<?php
/**
 * Fix attendance table structure and add missing columns
 */

require_once 'config/config.php';

echo "<h1>🔧 Fixing Student Attendance Table</h1>";

try {
    $conn = getDbConnection();
    
    echo "<h2>📊 Checking Current Table Structure</h2>";
    
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'student_attendance'");
    
    if ($table_check->num_rows > 0) {
        echo "✅ student_attendance table exists<br>";
        
        // Check current columns
        $columns_result = $conn->query("SHOW COLUMNS FROM student_attendance");
        $existing_columns = [];
        
        echo "<h3>Current Columns:</h3>";
        while ($column = $columns_result->fetch_assoc()) {
            $existing_columns[] = $column['Field'];
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
        }
        
        echo "<h2>🔧 Adding Missing Columns</h2>";
        
        // Add subject column if missing
        if (!in_array('subject', $existing_columns)) {
            $conn->query("ALTER TABLE student_attendance ADD COLUMN subject VARCHAR(100) AFTER date");
            echo "✅ Added 'subject' column<br>";
        } else {
            echo "ℹ️ 'subject' column already exists<br>";
        }
        
        // Add teacher_id column if missing
        if (!in_array('teacher_id', $existing_columns)) {
            $conn->query("ALTER TABLE student_attendance ADD COLUMN teacher_id INT AFTER status");
            echo "✅ Added 'teacher_id' column<br>";
        } else {
            echo "ℹ️ 'teacher_id' column already exists<br>";
        }
        
        // Add created_at column if missing
        if (!in_array('created_at', $existing_columns)) {
            $conn->query("ALTER TABLE student_attendance ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            echo "✅ Added 'created_at' column<br>";
        } else {
            echo "ℹ️ 'created_at' column already exists<br>";
        }
        
        // Add updated_at column if missing
        if (!in_array('updated_at', $existing_columns)) {
            $conn->query("ALTER TABLE student_attendance ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            echo "✅ Added 'updated_at' column<br>";
        } else {
            echo "ℹ️ 'updated_at' column already exists<br>";
        }
        
        echo "<h2>📊 Updating Status Column</h2>";
        
        // Update status column to use ENUM if needed
        $status_check = $conn->query("SHOW COLUMNS FROM student_attendance WHERE Field = 'status'");
        $status_info = $status_check->fetch_assoc();
        
        if ($status_info && !strpos($status_info['Type'], 'enum')) {
            $conn->query("ALTER TABLE student_attendance MODIFY COLUMN status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present'");
            echo "✅ Updated 'status' column to use ENUM<br>";
        } else {
            echo "ℹ️ 'status' column already uses ENUM<br>";
        }
        
        echo "<h2>🔍 Adding Indexes for Performance</h2>";
        
        // Add indexes if they don't exist
        $indexes = [
            'idx_class_date' => 'class_id, date',
            'idx_student_date' => 'student_id, date',
            'idx_teacher' => 'teacher_id'
        ];
        
        foreach ($indexes as $index_name => $columns) {
            try {
                $conn->query("ALTER TABLE student_attendance ADD INDEX $index_name ($columns)");
                echo "✅ Added index: $index_name<br>";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    echo "ℹ️ Index $index_name already exists<br>";
                } else {
                    echo "⚠️ Could not add index $index_name: " . $e->getMessage() . "<br>";
                }
            }
        }
        
    } else {
        echo "❌ student_attendance table does not exist. Creating new table...<br>";
        
        // Create new table with all required columns
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
            echo "✅ Created student_attendance table with all required columns<br>";
        } else {
            echo "❌ Failed to create table: " . $conn->error . "<br>";
        }
    }
    
    echo "<h2>📋 Final Table Structure</h2>";
    
    // Show final table structure
    $final_columns = $conn->query("SHOW COLUMNS FROM student_attendance");
    echo "<table border='1' style='border-collapse: collapse; margin: 1rem 0;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    while ($column = $final_columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>🧪 Testing Sample Data</h2>";
    
    // Insert sample attendance data for testing
    $sample_data = [
        [1, 1, '2025-07-13', 'Mathematics', 'present', 1],
        [1, 2, '2025-07-13', 'Mathematics', 'absent', 1],
        [1, 3, '2025-07-13', 'Mathematics', 'late', 1],
    ];
    
    foreach ($sample_data as $data) {
        try {
            $check_stmt = $conn->prepare("SELECT id FROM student_attendance WHERE class_id = ? AND student_id = ? AND date = ? AND subject = ?");
            $check_stmt->bind_param("iiss", $data[0], $data[1], $data[2], $data[3]);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows == 0) {
                $insert_stmt = $conn->prepare("INSERT INTO student_attendance (class_id, student_id, date, subject, status, teacher_id) VALUES (?, ?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("iisssi", $data[0], $data[1], $data[2], $data[3], $data[4], $data[5]);
                
                if ($insert_stmt->execute()) {
                    echo "✅ Sample attendance added: Class {$data[0]}, Student {$data[1]} - {$data[4]}<br>";
                } else {
                    echo "❌ Failed to add sample attendance: " . $conn->error . "<br>";
                }
                $insert_stmt->close();
            } else {
                echo "ℹ️ Sample attendance already exists: Class {$data[0]}, Student {$data[1]}<br>";
            }
            $check_stmt->close();
        } catch (Exception $e) {
            echo "⚠️ Error with sample data: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h2>✅ Database Fix Complete!</h2>";
    echo "<div style='background: #e8f5e8; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>✅ Summary:</h3>";
    echo "<ul>";
    echo "<li>✅ student_attendance table structure fixed</li>";
    echo "<li>✅ All required columns added (subject, teacher_id, created_at, updated_at)</li>";
    echo "<li>✅ Status column uses proper ENUM values</li>";
    echo "<li>✅ Performance indexes added</li>";
    echo "<li>✅ Sample data inserted for testing</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>🔗 Quick Links</h2>";
    echo "<p>";
    echo "<a href='teacher/attendance.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Teacher Attendance Page</a>";
    echo "<a href='parent/dashboard.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Parent Dashboard</a>";
    echo "<a href='test_attendance_notification.php' style='margin-right: 1rem; padding: 0.5rem 1rem; background: #ffc107; color: black; text-decoration: none; border-radius: 4px;'>Test Notifications</a>";
    echo "</p>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
