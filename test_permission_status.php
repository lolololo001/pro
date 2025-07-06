<?php
/**
 * Test script to verify permission status functionality
 * This script tests the permission request status updates
 */

require_once 'config/config.php';

echo "<h1>Permission Status Test</h1>";

try {
    $conn = getDbConnection();
    
    // Test 1: Check if permission_requests table exists
    echo "<h2>Test 1: Table Structure</h2>";
    $tableCheck = $conn->query("SHOW TABLES LIKE 'permission_requests'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        echo "✅ permission_requests table exists<br>";
        
        // Check table structure
        $columns = $conn->query("SHOW COLUMNS FROM permission_requests");
        echo "Table columns:<br>";
        while ($col = $columns->fetch_assoc()) {
            echo "- {$col['Field']}: {$col['Type']}<br>";
        }
    } else {
        echo "❌ permission_requests table does not exist<br>";
    }
    
    // Test 2: Check for sample permission requests
    echo "<h2>Test 2: Sample Data</h2>";
    $sampleData = $conn->query("SELECT * FROM permission_requests ORDER BY created_at DESC LIMIT 5");
    if ($sampleData && $sampleData->num_rows > 0) {
        echo "Found {$sampleData->num_rows} permission requests:<br>";
        while ($row = $sampleData->fetch_assoc()) {
            echo "- ID: {$row['id']}, Status: {$row['status']}, Created: {$row['created_at']}, Updated: " . (isset($row['updated_at']) ? $row['updated_at'] : 'NULL') . "<br>";
            if (isset($row['response_comment']) && !empty($row['response_comment'])) {
                echo "  Response: " . htmlspecialchars(substr($row['response_comment'], 0, 100)) . "<br>";
            }
        }
    } else {
        echo "No permission requests found<br>";
    }
    
    // Test 3: Check status distribution
    echo "<h2>Test 3: Status Distribution</h2>";
    $statusCounts = $conn->query("SELECT status, COUNT(*) as count FROM permission_requests GROUP BY status");
    if ($statusCounts) {
        while ($row = $statusCounts->fetch_assoc()) {
            echo "- {$row['status']}: {$row['count']}<br>";
        }
    }
    
    // Test 4: Check if students and parents tables exist
    echo "<h2>Test 4: Related Tables</h2>";
    $tables = ['students', 'parents', 'student_parent'];
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            echo "✅ $table table exists<br>";
        } else {
            echo "❌ $table table does not exist<br>";
        }
    }
    
    // Test 5: Check recent updates
    echo "<h2>Test 5: Recent Updates (Last 24 hours)</h2>";
    $recentUpdates = $conn->query("SELECT id, status, response_comment, updated_at FROM permission_requests WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY updated_at DESC");
    if ($recentUpdates && $recentUpdates->num_rows > 0) {
        echo "Found {$recentUpdates->num_rows} recent updates:<br>";
        while ($row = $recentUpdates->fetch_assoc()) {
            echo "- ID: {$row['id']}, Status: {$row['status']}, Updated: {$row['updated_at']}<br>";
        }
    } else {
        echo "No recent updates found<br>";
    }
    
    // Test 6: Test the exact query used in parent dashboard
    echo "<h2>Test 6: Parent Dashboard Query Test</h2>";
    $parentId = 1; // Test with parent ID 1
    $testQuery = "SELECT pr.id, pr.reason as request_text, pr.status, pr.created_at, pr.response_comment,
                  pr.start_date, pr.end_date, pr.request_type,
                  CONCAT(s.first_name, ' ', s.last_name) as student_name, s.admission_number as student_id 
                  FROM permission_requests pr 
                  LEFT JOIN students s ON pr.student_id = s.id 
                  WHERE pr.parent_id = $parentId 
                  ORDER BY pr.created_at DESC";
    
    $testResult = $conn->query($testQuery);
    if ($testResult && $testResult->num_rows > 0) {
        echo "Found {$testResult->num_rows} requests for parent ID $parentId:<br>";
        while ($row = $testResult->fetch_assoc()) {
            echo "- ID: {$row['id']}, Status: {$row['status']}, Student: {$row['student_name']}<br>";
        }
    } else {
        echo "No requests found for parent ID $parentId<br>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

echo "<h2>Test Complete</h2>";
echo "<p>This test verifies that the permission status system is properly set up.</p>";
?> 