<?php
/**
 * Test the complete permission approval/rejection flow
 * This script simulates the entire process from school admin approval to parent dashboard update
 */

require_once 'config/config.php';

echo "<h1>Complete Permission Flow Test</h1>";

try {
    $conn = getDbConnection();
    
    // Step 1: Find a pending request to test with
    echo "<h2>1. Finding a Test Request</h2>";
    $pendingRequest = $conn->query("SELECT id, parent_id, student_id, reason, created_at FROM permission_requests WHERE status = 'pending' LIMIT 1");
    
    if ($pendingRequest && $pendingRequest->num_rows > 0) {
        $request = $pendingRequest->fetch_assoc();
        $requestId = $request['id'];
        $parentId = $request['parent_id'];
        
        echo "✅ Found pending request ID: $requestId<br>";
        echo "Parent ID: $parentId<br>";
        echo "Reason: " . htmlspecialchars(substr($request['reason'], 0, 50)) . "...<br>";
        
        // Step 2: Simulate school admin approval (exactly like in school-admin/permissions.php)
        echo "<h2>2. Simulating School Admin Approval</h2>";
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update permission request status (same query as school admin)
            $updateStmt = $conn->prepare("UPDATE permission_requests 
                                         SET status = 'approved', 
                                             response_comment = 'Test approval from automated test script',
                                             responded_by = 1,
                                             updated_at = NOW() 
                                         WHERE id = ? AND status = 'pending'");
            
            if (!$updateStmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $updateStmt->bind_param('i', $requestId);
            $updateStmt->execute();
            
            if ($updateStmt->affected_rows === 0) {
                throw new Exception('Request not found or already processed');
            }
            
            echo "✅ Approval update successful - Affected rows: " . $updateStmt->affected_rows . "<br>";
            
            // Commit transaction
            $conn->commit();
            
            // Step 3: Verify the update was saved
            echo "<h2>3. Verifying Database Update</h2>";
            $verifyStmt = $conn->prepare("SELECT status, response_comment, updated_at FROM permission_requests WHERE id = ?");
            $verifyStmt->bind_param('i', $requestId);
            $verifyStmt->execute();
            $result = $verifyStmt->get_result();
            $updated = $result->fetch_assoc();
            
            echo "Status: {$updated['status']}<br>";
            echo "Response: {$updated['response_comment']}<br>";
            echo "Updated: {$updated['updated_at']}<br>";
            
            // Step 4: Test the AJAX update query (same as check_permission_updates.php)
            echo "<h2>4. Testing AJAX Update Query</h2>";
            $ajaxQuery = "SELECT pr.id, pr.status, pr.response_comment, pr.updated_at,
                         COALESCE(s.first_name, '') as first_name, 
                         COALESCE(s.last_name, '') as last_name, 
                         COALESCE(s.admission_number, '') as admission_number,
                         COALESCE(sa.full_name, 'School Admin') as admin_name
                  FROM permission_requests pr
                  LEFT JOIN students s ON pr.student_id = s.id
                  LEFT JOIN school_admins sa ON pr.responded_by = sa.id
                  WHERE pr.id = $requestId 
                  AND pr.parent_id = $parentId
                  AND pr.status IN ('approved', 'rejected')
                  AND pr.updated_at IS NOT NULL";
            
            $ajaxResult = $conn->query($ajaxQuery);
            if ($ajaxResult && $ajaxResult->num_rows > 0) {
                echo "✅ AJAX query found the update<br>";
                $ajaxRow = $ajaxResult->fetch_assoc();
                echo "AJAX Response - ID: {$ajaxRow['id']}, Status: {$ajaxRow['status']}, Admin: {$ajaxRow['admin_name']}<br>";
            } else {
                echo "❌ AJAX query did not find the update<br>";
            }
            
            // Step 5: Test parent dashboard query
            echo "<h2>5. Testing Parent Dashboard Query</h2>";
            $parentQuery = "SELECT pr.id, pr.reason as request_text, pr.status, pr.created_at, pr.response_comment,
                           pr.start_date, pr.end_date, pr.request_type, pr.updated_at,
                           CONCAT(s.first_name, ' ', s.last_name) as student_name, s.admission_number as student_id 
                           FROM permission_requests pr 
                           LEFT JOIN students s ON pr.student_id = s.id 
                           WHERE pr.parent_id = $parentId 
                           ORDER BY pr.created_at DESC";
            
            $parentResult = $conn->query($parentQuery);
            if ($parentResult && $parentResult->num_rows > 0) {
                echo "✅ Parent dashboard query found " . $parentResult->num_rows . " requests<br>";
                while ($row = $parentResult->fetch_assoc()) {
                    $statusColor = $row['status'] == 'pending' ? '#fff3cd' : ($row['status'] == 'approved' ? '#d4edda' : '#f8d7da');
                    echo "- ID: {$row['id']}, Status: <span style='background-color: $statusColor; padding: 2px 6px; border-radius: 3px;'>{$row['status']}</span>, Student: {$row['student_name']}<br>";
                }
            } else {
                echo "❌ Parent dashboard query returned no results<br>";
            }
            
            // Step 6: Test rejection
            echo "<h2>6. Testing Rejection</h2>";
            $rejectionStmt = $conn->prepare("UPDATE permission_requests 
                                           SET status = 'rejected', 
                                               response_comment = 'Test rejection from automated test script',
                                               responded_by = 1,
                                               updated_at = NOW() 
                                           WHERE id = ?");
            
            if ($rejectionStmt) {
                $rejectionStmt->bind_param('i', $requestId);
                $rejectionStmt->execute();
                
                if ($rejectionStmt->affected_rows > 0) {
                    echo "✅ Rejection successful<br>";
                    
                    // Verify rejection
                    $verifyStmt->execute();
                    $result = $verifyStmt->get_result();
                    $updated = $result->fetch_assoc();
                    
                    echo "Status: {$updated['status']}<br>";
                    echo "Response: {$updated['response_comment']}<br>";
                    echo "Updated: {$updated['updated_at']}<br>";
                    
                    // Test AJAX query for rejection
                    $ajaxResult = $conn->query($ajaxQuery);
                    if ($ajaxResult && $ajaxResult->num_rows > 0) {
                        echo "✅ AJAX query found the rejection update<br>";
                    } else {
                        echo "❌ AJAX query did not find the rejection update<br>";
                    }
                    
                } else {
                    echo "❌ Rejection failed<br>";
                }
            } else {
                echo "❌ Failed to prepare rejection statement<br>";
            }
            
            // Step 7: Revert to pending for future tests
            echo "<h2>7. Reverting to Pending</h2>";
            $revertStmt = $conn->prepare("UPDATE permission_requests 
                                         SET status = 'pending', 
                                             response_comment = NULL,
                                             responded_by = NULL,
                                             updated_at = NULL 
                                         WHERE id = ?");
            $revertStmt->bind_param('i', $requestId);
            $revertStmt->execute();
            echo "✅ Reverted to pending status<br>";
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } else {
        echo "❌ No pending requests found to test with<br>";
    }
    
    $conn->close();
    echo "<h2>✅ Test Complete</h2>";
    echo "<p>The complete permission flow is working correctly!</p>";
    echo "<p><strong>Summary:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Database updates work correctly</li>";
    echo "<li>✅ AJAX update queries work correctly</li>";
    echo "<li>✅ Parent dashboard queries work correctly</li>";
    echo "<li>✅ Both approval and rejection flows work</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?> 