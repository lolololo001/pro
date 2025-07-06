<?php
/**
 * Test script to manually test permission status updates
 */

require_once 'config/config.php';

echo "<h1>Permission Update Test</h1>";

try {
    $conn = getDbConnection();
    
    // Test 1: Check current permission requests
    echo "<h2>1. Current Permission Requests</h2>";
    $currentRequests = $conn->query("SELECT id, parent_id, student_id, status, response_comment, created_at, updated_at FROM permission_requests ORDER BY created_at DESC LIMIT 5");
    if ($currentRequests && $currentRequests->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Parent ID</th><th>Student ID</th><th>Status</th><th>Response</th><th>Created</th><th>Updated</th></tr>";
        while ($row = $currentRequests->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['parent_id']}</td>";
            echo "<td>{$row['student_id']}</td>";
            echo "<td style='background-color: " . ($row['status'] == 'pending' ? '#fff3cd' : ($row['status'] == 'approved' ? '#d4edda' : '#f8d7da')) . "'>{$row['status']}</td>";
            echo "<td>" . (isset($row['response_comment']) ? htmlspecialchars(substr($row['response_comment'], 0, 30)) : 'NULL') . "</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "<td>" . (isset($row['updated_at']) ? $row['updated_at'] : 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No permission requests found.<br>";
    }
    
    // Test 2: Try to update a pending request
    echo "<h2>2. Test Update</h2>";
    $pendingRequest = $conn->query("SELECT id FROM permission_requests WHERE status = 'pending' LIMIT 1");
    if ($pendingRequest && $pendingRequest->num_rows > 0) {
        $request = $pendingRequest->fetch_assoc();
        $requestId = $request['id'];
        
        echo "Found pending request ID: $requestId<br>";
        
        // Try to update it
        $updateStmt = $conn->prepare("UPDATE permission_requests 
                                     SET status = 'approved', 
                                         response_comment = 'Test approval from debug script',
                                         responded_by = 1,
                                         updated_at = NOW() 
                                     WHERE id = ? AND status = 'pending'");
        
        if ($updateStmt) {
            $updateStmt->bind_param('i', $requestId);
            $updateStmt->execute();
            
            echo "Update attempt - Affected rows: " . $updateStmt->affected_rows . "<br>";
            
            if ($updateStmt->affected_rows > 0) {
                echo "✅ Update successful!<br>";
                
                // Verify the update
                $verifyStmt = $conn->prepare("SELECT status, response_comment, updated_at FROM permission_requests WHERE id = ?");
                $verifyStmt->bind_param('i', $requestId);
                $verifyStmt->execute();
                $result = $verifyStmt->get_result();
                $updated = $result->fetch_assoc();
                
                echo "Verification - Status: {$updated['status']}, Response: {$updated['response_comment']}, Updated: {$updated['updated_at']}<br>";
                
                // Revert the test update
                $revertStmt = $conn->prepare("UPDATE permission_requests 
                                             SET status = 'pending', 
                                                 response_comment = NULL,
                                                 responded_by = NULL,
                                                 updated_at = NULL 
                                             WHERE id = ?");
                $revertStmt->bind_param('i', $requestId);
                $revertStmt->execute();
                echo "✅ Test update reverted<br>";
                
            } else {
                echo "❌ Update failed - no rows affected<br>";
            }
        } else {
            echo "❌ Failed to prepare update statement: " . $conn->error . "<br>";
        }
    } else {
        echo "No pending requests found to test with.<br>";
    }
    
    // Test 3: Check table structure
    echo "<h2>3. Table Structure</h2>";
    $columns = $conn->query("SHOW COLUMNS FROM permission_requests");
    if ($columns) {
        echo "Permission requests table columns:<br>";
        while ($col = $columns->fetch_assoc()) {
            echo "- {$col['Field']}: {$col['Type']} " . ($col['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "<br>";
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}

echo "<h2>Test Complete</h2>";
echo "<p>This test verifies that permission status updates are working correctly.</p>";
?> 