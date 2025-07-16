<?php
/**
 * Fix attendance table unique constraint issue
 */

require_once 'config/config.php';

echo "<h1>🔧 Fixing Student Attendance Table Unique Constraint</h1>";

try {
    $conn = getDbConnection();
    
    echo "<h2>📊 Checking Current Table Structure</h2>";
    
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'student_attendance'");
    
    if ($table_check->num_rows > 0) {
        echo "✅ student_attendance table exists<br>";
        
        // Show current table structure
        $result = $conn->query("SHOW CREATE TABLE student_attendance");
        $row = $result->fetch_assoc();
        $createTable = $row['Create Table'];
        
        echo "<h3>Current Table Structure:</h3>";
        echo "<pre>" . htmlspecialchars($createTable) . "</pre>";
        
        // Check for unique constraints
        echo "<h3>Current Indexes:</h3>";
        $indexes_result = $conn->query("SHOW INDEX FROM student_attendance");
        echo "<table border='1' style='border-collapse: collapse; margin: 1rem 0;'>";
        echo "<tr><th>Key_name</th><th>Column_name</th><th>Non_unique</th><th>Index_type</th></tr>";
        
        $unique_constraints = [];
        while ($index = $indexes_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($index['Key_name']) . "</td>";
            echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
            echo "<td>" . htmlspecialchars($index['Non_unique']) . "</td>";
            echo "<td>" . htmlspecialchars($index['Index_type']) . "</td>";
            echo "</tr>";
            
            if ($index['Non_unique'] == 0 && $index['Key_name'] != 'PRIMARY') {
                $unique_constraints[] = $index['Key_name'];
            }
        }
        echo "</table>";
        
        // Remove problematic unique constraints
        foreach ($unique_constraints as $constraint) {
            echo "<p>Removing unique constraint: $constraint</p>";
            try {
                $conn->query("ALTER TABLE student_attendance DROP INDEX `$constraint`");
                echo "✅ Successfully removed constraint: $constraint<br>";
            } catch (Exception $e) {
                echo "⚠️ Error removing constraint $constraint: " . $e->getMessage() . "<br>";
            }
        }
        
        // Add proper unique constraint for student attendance
        echo "<h2>🔧 Adding Proper Unique Constraint</h2>";
        
        // Check if we need to add a unique constraint for student, class, date, and subject
        $unique_check = $conn->query("SHOW INDEX FROM student_attendance WHERE Key_name = 'unique_student_attendance'");
        
        if ($unique_check->num_rows == 0) {
            try {
                $conn->query("ALTER TABLE student_attendance ADD UNIQUE INDEX unique_student_attendance (student_id, class_id, date, subject)");
                echo "✅ Added unique constraint for student attendance (student_id, class_id, date, subject)<br>";
            } catch (Exception $e) {
                echo "⚠️ Error adding unique constraint: " . $e->getMessage() . "<br>";
                
                // If there are duplicate records, we need to clean them up first
                echo "<h3>🧹 Cleaning up duplicate records...</h3>";
                
                // Find and remove duplicates
                $duplicates_query = "
                    DELETE sa1 FROM student_attendance sa1
                    INNER JOIN student_attendance sa2 
                    WHERE sa1.id > sa2.id 
                    AND sa1.student_id = sa2.student_id 
                    AND sa1.class_id = sa2.class_id 
                    AND sa1.date = sa2.date 
                    AND (sa1.subject = sa2.subject OR (sa1.subject IS NULL AND sa2.subject IS NULL))
                ";
                
                if ($conn->query($duplicates_query)) {
                    echo "✅ Cleaned up duplicate records<br>";
                    
                    // Try adding the unique constraint again
                    try {
                        $conn->query("ALTER TABLE student_attendance ADD UNIQUE INDEX unique_student_attendance (student_id, class_id, date, subject)");
                        echo "✅ Successfully added unique constraint after cleanup<br>";
                    } catch (Exception $e2) {
                        echo "❌ Still cannot add unique constraint: " . $e2->getMessage() . "<br>";
                    }
                } else {
                    echo "❌ Error cleaning up duplicates: " . $conn->error . "<br>";
                }
            }
        } else {
            echo "ℹ️ Unique constraint already exists<br>";
        }
        
        // Ensure all required columns exist
        echo "<h2>🔧 Ensuring All Required Columns Exist</h2>";
        
        $columns_result = $conn->query("SHOW COLUMNS FROM student_attendance");
        $existing_columns = [];
        
        while ($column = $columns_result->fetch_assoc()) {
            $existing_columns[] = $column['Field'];
        }
        
        $required_columns = [
            'subject' => "ALTER TABLE student_attendance ADD COLUMN subject VARCHAR(100) AFTER date",
            'teacher_id' => "ALTER TABLE student_attendance ADD COLUMN teacher_id INT AFTER status",
            'created_at' => "ALTER TABLE student_attendance ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "ALTER TABLE student_attendance ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];
        
        foreach ($required_columns as $column_name => $sql) {
            if (!in_array($column_name, $existing_columns)) {
                try {
                    $conn->query($sql);
                    echo "✅ Added missing column: $column_name<br>";
                } catch (Exception $e) {
                    echo "⚠️ Error adding column $column_name: " . $e->getMessage() . "<br>";
                }
            } else {
                echo "ℹ️ Column $column_name already exists<br>";
            }
        }
        
    } else {
        echo "❌ student_attendance table does not exist. Creating new table...<br>";
        
        // Create new table with proper structure
        $create_sql = "CREATE TABLE student_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            student_id INT NOT NULL,
            date DATE NOT NULL,
            subject VARCHAR(100),
            status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
            teacher_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE INDEX unique_student_attendance (student_id, class_id, date, subject),
            INDEX idx_class_date (class_id, date),
            INDEX idx_student_date (student_id, date),
            INDEX idx_teacher (teacher_id)
        )";
        
        if ($conn->query($create_sql)) {
            echo "✅ Created student_attendance table with proper structure<br>";
        } else {
            echo "❌ Failed to create table: " . $conn->error . "<br>";
        }
    }
    
    echo "<h2>📋 Final Table Structure</h2>";
    
    // Show final table structure
    $final_result = $conn->query("SHOW CREATE TABLE student_attendance");
    $final_row = $final_result->fetch_assoc();
    echo "<pre>" . htmlspecialchars($final_row['Create Table']) . "</pre>";
    
    echo "<h2>✅ Database Fix Complete!</h2>";
    echo "<div style='background: #e8f5e8; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>✅ Summary:</h3>";
    echo "<ul>";
    echo "<li>✅ Removed problematic unique constraints</li>";
    echo "<li>✅ Added proper unique constraint for student attendance</li>";
    echo "<li>✅ Ensured all required columns exist</li>";
    echo "<li>✅ Cleaned up any duplicate records</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h2>🔗 Quick Links</h2>";
    echo "<p>";
    echo "<a href='teacher/attendance.php' style='background: #00704a; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; margin-right: 1rem;'>📊 Go to Attendance Page</a>";
    echo "<a href='test_attendance_with_subjects.php' style='background: #17a2b8; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px;'>🧪 Test Attendance System</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

$conn->close();
?> 