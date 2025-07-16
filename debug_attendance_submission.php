<?php
/**
 * Debug script to test attendance submission process
 */

require_once 'config/config.php';

echo "<h1>🔍 Debug Attendance Submission Process</h1>";

try {
    $conn = getDbConnection();
    
    echo "<h2>📊 Database Connection Test</h2>";
    echo "✅ Database connection successful<br>";
    
    echo "<h2>📋 Table Structure Check</h2>";
    
    // Check if student_attendance table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'student_attendance'");
    if ($table_check->num_rows > 0) {
        echo "✅ student_attendance table exists<br>";
        
        // Show table structure
        $structure = $conn->query("DESCRIBE student_attendance");
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 1rem 0;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ student_attendance table does not exist<br>";
    }
    
    echo "<h2>👥 Available Data Check</h2>";
    
    // Check for available schools
    $schools = $conn->query("SELECT COUNT(*) as count FROM schools")->fetch_assoc()['count'];
    echo "✅ Schools: $schools<br>";
    
    // Check for available classes
    $classes = $conn->query("SELECT COUNT(*) as count FROM classes")->fetch_assoc()['count'];
    echo "✅ Classes: $classes<br>";
    
    // Check for available students
    $students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
    echo "✅ Students: $students<br>";
    
    // Check for available teachers
    $teachers = $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
    echo "✅ Teachers: $teachers<br>";
    
    echo "<h2>🧪 Test Attendance Submission</h2>";
    
    // Get sample data for testing
    $sample_class = $conn->query("SELECT id, class_name FROM classes LIMIT 1")->fetch_assoc();
    $sample_student = $conn->query("SELECT id, first_name, last_name FROM students LIMIT 1")->fetch_assoc();
    $sample_teacher = $conn->query("SELECT id, name FROM teachers LIMIT 1")->fetch_assoc();
    
    if ($sample_class && $sample_student && $sample_teacher) {
        echo "✅ Found sample data for testing<br>";
        echo "Class: {$sample_class['class_name']} (ID: {$sample_class['id']})<br>";
        echo "Student: {$sample_student['first_name']} {$sample_student['last_name']} (ID: {$sample_student['id']})<br>";
        echo "Teacher: {$sample_teacher['name']} (ID: {$sample_teacher['id']})<br>";
        
        // Test attendance insertion
        $test_date = date('Y-m-d');
        $test_subject = 'Test Subject';
        $test_status = 'present';
        
        echo "<h3>Testing Direct Database Insertion:</h3>";
        
        // Check if attendance already exists
        $check_stmt = $conn->prepare("SELECT id FROM student_attendance WHERE class_id = ? AND student_id = ? AND date = ? AND subject = ?");
        $check_stmt->bind_param("iiss", $sample_class['id'], $sample_student['id'], $test_date, $test_subject);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Insert test attendance
            $insert_stmt = $conn->prepare("INSERT INTO student_attendance (class_id, student_id, date, subject, status, teacher_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $insert_stmt->bind_param("iisssi", $sample_class['id'], $sample_student['id'], $test_date, $test_subject, $test_status, $sample_teacher['id']);
            
            if ($insert_stmt->execute()) {
                echo "✅ Test attendance inserted successfully<br>";
                echo "Insert ID: " . $conn->insert_id . "<br>";
                
                // Verify the insertion
                $verify_stmt = $conn->prepare("SELECT * FROM student_attendance WHERE id = ?");
                $verify_stmt->bind_param("i", $conn->insert_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                $attendance_record = $verify_result->fetch_assoc();
                
                echo "<h4>Inserted Record:</h4>";
                echo "<pre>" . print_r($attendance_record, true) . "</pre>";
                
                // Clean up test data
                $delete_stmt = $conn->prepare("DELETE FROM student_attendance WHERE id = ?");
                $delete_stmt->bind_param("i", $conn->insert_id);
                $delete_stmt->execute();
                echo "✅ Test data cleaned up<br>";
                
            } else {
                echo "❌ Failed to insert test attendance: " . $conn->error . "<br>";
            }
            $insert_stmt->close();
        } else {
            echo "ℹ️ Test attendance already exists, skipping insertion test<br>";
        }
        $check_stmt->close();
        
    } else {
        echo "❌ Missing sample data for testing<br>";
        echo "Please ensure you have at least one class, student, and teacher in the database.<br>";
    }
    
    echo "<h2>📝 Form Submission Test</h2>";
    
    // Simulate form data
    $form_data = [
        'take_attendance' => '1',
        'class_id' => $sample_class['id'] ?? 1,
        'date' => date('Y-m-d'),
        'subject' => 'Test Subject',
        'attendance' => [
            ($sample_student['id'] ?? 1) => 'present'
        ]
    ];
    
    echo "<h3>Simulated Form Data:</h3>";
    echo "<pre>" . print_r($form_data, true) . "</pre>";
    
    echo "<h2>🔗 Test Real Form Submission</h2>";
    echo "<p>To test the actual form submission, go to the attendance page and try saving attendance:</p>";
    echo "<p>";
    echo "<a href='teacher/attendance.php' style='background: #00704a; color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; margin: 1rem 0;'>";
    echo "🧪 Test Attendance Form";
    echo "</a>";
    echo "</p>";
    
    echo "<h2>📊 Current Attendance Records</h2>";
    
    $attendance_count = $conn->query("SELECT COUNT(*) as count FROM student_attendance")->fetch_assoc()['count'];
    echo "Total attendance records: $attendance_count<br>";
    
    if ($attendance_count > 0) {
        $recent_attendance = $conn->query("SELECT * FROM student_attendance ORDER BY created_at DESC LIMIT 5");
        echo "<h3>Recent Attendance Records:</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 1rem 0;'>";
        echo "<tr><th>ID</th><th>Class ID</th><th>Student ID</th><th>Date</th><th>Subject</th><th>Status</th><th>Teacher ID</th><th>Created</th></tr>";
        while ($row = $recent_attendance->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['class_id'] . "</td>";
            echo "<td>" . $row['student_id'] . "</td>";
            echo "<td>" . $row['date'] . "</td>";
            echo "<td>" . $row['subject'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['teacher_id'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h2>✅ Debug Summary</h2>";
    echo "<div style='background: #e8f5e8; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>✅ System Status:</h3>";
    echo "<ul>";
    echo "<li>✅ Database connection: Working</li>";
    echo "<li>✅ student_attendance table: " . ($table_check->num_rows > 0 ? 'Exists' : 'Missing') . "</li>";
    echo "<li>✅ Sample data: " . ($sample_class && $sample_student && $sample_teacher ? 'Available' : 'Missing') . "</li>";
    echo "<li>✅ Direct insertion: " . (isset($attendance_record) ? 'Working' : 'Not tested') . "</li>";
    echo "<li>✅ Current records: $attendance_count</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

$conn->close();
?> 