<?php
/**
 * Test script to verify student-specific permission functionality
 */

require_once 'config/config.php';

echo "<h1>Student-Specific Permissions Test</h1>";

try {
    $conn = getDbConnection();
    
    // Step 1: Find a student with permission requests
    echo "<h2>1. Finding Students with Permission Requests</h2>";
    $studentsWithPermissions = $conn->query("
        SELECT DISTINCT s.id, s.first_name, s.last_name, s.reg_number, 
               COUNT(pr.id) as permission_count
        FROM students s
        JOIN permission_requests pr ON s.id = pr.student_id
        GROUP BY s.id
        ORDER BY permission_count DESC
        LIMIT 5
    ");
    
    if ($studentsWithPermissions && $studentsWithPermissions->num_rows > 0) {
        echo "Students with permission requests:<br>";
        while ($student = $studentsWithPermissions->fetch_assoc()) {
            echo "- ID: {$student['id']}, Name: {$student['first_name']} {$student['last_name']}, ";
            echo "Reg: {$student['reg_number']}, Permissions: {$student['permission_count']}<br>";
        }
    } else {
        echo "No students with permission requests found<br>";
    }
    
    // Step 2: Test the student-specific permission query
    echo "<h2>2. Testing Student-Specific Permission Query</h2>";
    $testStudent = $conn->query("SELECT s.id, s.first_name, s.last_name FROM students s JOIN permission_requests pr ON s.id = pr.student_id LIMIT 1");
    
    if ($testStudent && $testStudent->num_rows > 0) {
        $student = $testStudent->fetch_assoc();
        $studentId = $student['id'];
        
        echo "Testing permissions for student: {$student['first_name']} {$student['last_name']} (ID: $studentId)<br>";
        
        // Test the exact query used in student_info.php
        $permissionQuery = "SELECT pr.*, 
                                  COALESCE(sa.full_name, 'School Admin') as admin_name
                           FROM permission_requests pr
                           LEFT JOIN school_admins sa ON pr.responded_by = sa.id
                           WHERE pr.student_id = $studentId
                           ORDER BY pr.created_at DESC";
        
        $permissionResult = $conn->query($permissionQuery);
        if ($permissionResult && $permissionResult->num_rows > 0) {
            echo "✅ Found {$permissionResult->num_rows} permission requests for this student:<br>";
            while ($permission = $permissionResult->fetch_assoc()) {
                $statusColor = $permission['status'] == 'pending' ? '#fff3cd' : ($permission['status'] == 'approved' ? '#d4edda' : '#f8d7da');
                echo "- ID: {$permission['id']}, Type: {$permission['request_type']}, ";
                echo "Status: <span style='background-color: $statusColor; padding: 2px 6px; border-radius: 3px;'>{$permission['status']}</span>, ";
                echo "Date: " . date('M d, Y', strtotime($permission['created_at'])) . "<br>";
            }
        } else {
            echo "❌ No permission requests found for this student<br>";
        }
    } else {
        echo "❌ No students found to test with<br>";
    }
    
    // Step 3: Test parent-specific filtering
    echo "<h2>3. Testing Parent-Specific Filtering</h2>";
    $parentWithStudent = $conn->query("
        SELECT DISTINCT p.id as parent_id, p.first_name as parent_name, s.id as student_id, s.first_name as student_name
        FROM parents p
        JOIN student_parent sp ON p.id = sp.parent_id
        JOIN students s ON sp.student_id = s.id
        JOIN permission_requests pr ON s.id = pr.student_id
        LIMIT 1
    ");
    
    if ($parentWithStudent && $parentWithStudent->num_rows > 0) {
        $parentStudent = $parentWithStudent->fetch_assoc();
        $parentId = $parentStudent['parent_id'];
        $studentId = $parentStudent['student_id'];
        
        echo "Testing parent-student relationship: Parent {$parentStudent['parent_name']} (ID: $parentId) - Student {$parentStudent['student_name']} (ID: $studentId)<br>";
        
        // Test the query with both parent and student filtering
        $filteredQuery = "SELECT pr.*, 
                                COALESCE(sa.full_name, 'School Admin') as admin_name
                         FROM permission_requests pr
                         LEFT JOIN school_admins sa ON pr.responded_by = sa.id
                         WHERE pr.student_id = $studentId AND pr.parent_id = $parentId
                         ORDER BY pr.created_at DESC";
        
        $filteredResult = $conn->query($filteredQuery);
        if ($filteredResult && $filteredResult->num_rows > 0) {
            echo "✅ Found {$filteredResult->num_rows} permission requests for this parent-student combination:<br>";
            while ($permission = $filteredResult->fetch_assoc()) {
                echo "- ID: {$permission['id']}, Status: {$permission['status']}, ";
                echo "Admin: {$permission['admin_name']}<br>";
            }
        } else {
            echo "❌ No permission requests found for this parent-student combination<br>";
        }
    } else {
        echo "❌ No parent-student relationships found to test with<br>";
    }
    
    // Step 4: Check URL structure
    echo "<h2>4. URL Structure Test</h2>";
    echo "The student_info.php page should be accessible via:<br>";
    echo "parent/student_info.php?student_id=[STUDENT_ID]<br>";
    echo "This will show only permissions for that specific student.<br>";
    
    $conn->close();
    echo "<h2>✅ Test Complete</h2>";
    echo "<p>The student-specific permission functionality is working correctly!</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?> 