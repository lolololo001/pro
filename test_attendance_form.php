<?php
/**
 * Test script to simulate attendance form submission
 */

require_once 'config/config.php';

echo "<h1>🧪 Test Attendance Form Submission</h1>";

try {
    $conn = getDbConnection();
    
    // Get sample data
    $sample_class = $conn->query("SELECT id, class_name FROM classes LIMIT 1")->fetch_assoc();
    $sample_student = $conn->query("SELECT id, first_name, last_name FROM students LIMIT 1")->fetch_assoc();
    $sample_teacher = $conn->query("SELECT id, name FROM teachers LIMIT 1")->fetch_assoc();
    
    if (!$sample_class || !$sample_student || !$sample_teacher) {
        echo "❌ Missing required data for testing<br>";
        exit;
    }
    
    echo "<h2>📋 Sample Data</h2>";
    echo "Class: {$sample_class['class_name']} (ID: {$sample_class['id']})<br>";
    echo "Student: {$sample_student['first_name']} {$sample_student['last_name']} (ID: {$sample_student['id']})<br>";
    echo "Teacher: {$sample_teacher['name']} (ID: {$sample_teacher['id']})<br>";
    
    echo "<h2>🧪 Simulating Form Submission</h2>";
    
    // Simulate the exact form submission process
    $class_id = intval($sample_class['id']);
    $date = date('Y-m-d');
    $subject = 'Test Subject';
    $teacher_id = $sample_teacher['id'];
    
    // Simulate attendance data (like from form)
    $attendance_data = [
        $sample_student['id'] => 'present'
    ];
    
    echo "<h3>Processing Parameters:</h3>";
    echo "Class ID: $class_id<br>";
    echo "Date: $date<br>";
    echo "Subject: $subject<br>";
    echo "Teacher ID: $teacher_id<br>";
    echo "Students: " . count($attendance_data) . "<br>";
    
    $notification_count = 0;
    $attendance_summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
    $processed_count = 0;
    $error_count = 0;
    
    echo "<h3>Processing Each Student:</h3>";
    
    // Process each student's attendance (exact same logic as attendance.php)
    foreach ($attendance_data as $student_id => $status) {
        echo "<h4>Processing Student ID: $student_id, Status: $status</h4>";
        
        // Check if attendance already exists
        $check_query = "SELECT id FROM student_attendance WHERE class_id = ? AND student_id = ? AND date = ?";
        $check_params = [$class_id, $student_id, $date];
        $check_types = 'iis';
        
        if (!empty($subject)) {
            $check_query .= " AND subject = ?";
            $check_params[] = $subject;
            $check_types .= 's';
        }
        
        echo "Check Query: $check_query<br>";
        echo "Check Params: " . implode(', ', $check_params) . "<br>";
        
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param($check_types, ...$check_params);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        echo "Existing records found: " . $result->num_rows . "<br>";
        
        if ($result->num_rows > 0) {
            // Update existing attendance
            echo "Updating existing attendance...<br>";
            $update_query = "UPDATE student_attendance SET status = ?, updated_at = NOW() WHERE class_id = ? AND student_id = ? AND date = ?";
            $update_params = [$status, $class_id, $student_id, $date];
            $update_types = 'siis';

            if (!empty($subject)) {
                $update_query .= " AND subject = ?";
                $update_params[] = $subject;
                $update_types .= 's';
            }

            echo "Update Query: $update_query<br>";
            echo "Update Params: " . implode(', ', $update_params) . "<br>";

            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param($update_types, ...$update_params);

            if ($update_stmt->execute()) {
                echo "✅ Update successful<br>";
                $attendance_summary[$status]++;
                $processed_count++;
            } else {
                echo "❌ Update failed: " . $conn->error . "<br>";
                $error_count++;
            }
            $update_stmt->close();
        } else {
            // Insert new attendance
            echo "Inserting new attendance...<br>";
            $insert_query = "INSERT INTO student_attendance (class_id, student_id, date, status, teacher_id, subject, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $insert_params = [$class_id, $student_id, $date, $status, $teacher_id, $subject];
            
            echo "Insert Query: $insert_query<br>";
            echo "Insert Params: " . implode(', ', $insert_params) . "<br>";
            
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('iissis', ...$insert_params);

            if ($insert_stmt->execute()) {
                echo "✅ Insert successful (ID: " . $conn->insert_id . ")<br>";
                $attendance_summary[$status]++;
                $processed_count++;
            } else {
                echo "❌ Insert failed: " . $conn->error . "<br>";
                $error_count++;
            }
            $insert_stmt->close();
        }
        
        $check_stmt->close();
        echo "<hr>";
    }
    
    echo "<h2>📊 Processing Results</h2>";
    echo "Processed: $processed_count<br>";
    echo "Errors: $error_count<br>";
    echo "Present: {$attendance_summary['present']}<br>";
    echo "Absent: {$attendance_summary['absent']}<br>";
    echo "Late: {$attendance_summary['late']}<br>";
    echo "Excused: {$attendance_summary['excused']}<br>";
    
    if ($error_count == 0) {
        echo "<div style='background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
        echo "<h3>✅ Test Successful!</h3>";
        echo "<p>The attendance submission logic is working correctly.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
        echo "<h3>❌ Test Failed!</h3>";
        echo "<p>There were $error_count errors during processing.</p>";
        echo "</div>";
    }
    
    echo "<h2>🔗 Test Real Form</h2>";
    echo "<p>Now test the actual attendance form:</p>";
    echo "<p>";
    echo "<a href='teacher/attendance.php' style='background: #00704a; color: white; padding: 1rem 2rem; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; margin: 1rem 0;'>";
    echo "🧪 Test Real Attendance Form";
    echo "</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin: 1rem 0;'>";
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

$conn->close();
?> 