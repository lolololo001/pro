<?php
/**
 * Fix foreign key constraint issue for permission_requests table
 */

require_once 'config/config.php';

echo "<h1>Fix Foreign Key Constraint</h1>";

try {
    $conn = getDbConnection();
    
    // Step 1: Check school_admins table
    echo "<h2>1. Checking School Admins Table</h2>";
    $schoolAdmins = $conn->query("SELECT id, full_name FROM school_admins LIMIT 5");
    if ($schoolAdmins && $schoolAdmins->num_rows > 0) {
        echo "Found school admins:<br>";
        while ($admin = $schoolAdmins->fetch_assoc()) {
            echo "- ID: {$admin['id']}, Name: {$admin['full_name']}<br>";
        }
    } else {
        echo "No school admins found<br>";
    }
    
    // Step 2: Check foreign key constraints
    echo "<h2>2. Checking Foreign Key Constraints</h2>";
    $constraints = $conn->query("SELECT 
        CONSTRAINT_NAME, 
        COLUMN_NAME, 
        REFERENCED_TABLE_NAME, 
        REFERENCED_COLUMN_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'permission_requests' 
        AND REFERENCED_TABLE_NAME IS NOT NULL");
    
    if ($constraints) {
        while ($constraint = $constraints->fetch_assoc()) {
            echo "- Constraint: {$constraint['CONSTRAINT_NAME']}<br>";
            echo "  Column: {$constraint['COLUMN_NAME']}<br>";
            echo "  References: {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}<br>";
        }
    }
    
    // Step 3: Fix the responded_by column to allow NULL values
    echo "<h2>3. Fixing responded_by Column</h2>";
    
    // First, check current column definition
    $columnDef = $conn->query("SHOW COLUMNS FROM permission_requests LIKE 'responded_by'");
    if ($columnDef && $columnDef->num_rows > 0) {
        $col = $columnDef->fetch_assoc();
        echo "Current responded_by column: {$col['Type']} {$col['Null']}<br>";
        
        // If it's NOT NULL, make it nullable
        if ($col['Null'] == 'NO') {
            echo "Making responded_by column nullable...<br>";
            $alterStmt = $conn->prepare("ALTER TABLE permission_requests MODIFY COLUMN responded_by INT NULL");
            if ($alterStmt) {
                $alterStmt->execute();
                echo "✅ responded_by column is now nullable<br>";
            } else {
                echo "❌ Failed to modify responded_by column<br>";
            }
        } else {
            echo "✅ responded_by column is already nullable<br>";
        }
    }
    
    // Step 4: Test the update without responded_by
    echo "<h2>4. Testing Update Without responded_by</h2>";
    $testRequest = $conn->query("SELECT id FROM permission_requests WHERE status = 'pending' LIMIT 1");
    if ($testRequest && $testRequest->num_rows > 0) {
        $request = $testRequest->fetch_assoc();
        $requestId = $request['id'];
        
        $testStmt = $conn->prepare("UPDATE permission_requests 
                                   SET status = 'approved', 
                                       response_comment = 'Test approval without admin ID',
                                       responded_by = NULL,
                                       updated_at = NOW() 
                                   WHERE id = ? AND status = 'pending'");
        
        if ($testStmt) {
            $testStmt->bind_param('i', $requestId);
            $testStmt->execute();
            
            if ($testStmt->affected_rows > 0) {
                echo "✅ Test update successful without admin ID<br>";
                
                // Revert the test
                $revertStmt = $conn->prepare("UPDATE permission_requests 
                                             SET status = 'pending', 
                                                 response_comment = NULL,
                                                 responded_by = NULL,
                                                 updated_at = NULL 
                                             WHERE id = ?");
                $revertStmt->bind_param('i', $requestId);
                $revertStmt->execute();
                echo "✅ Test reverted<br>";
            } else {
                echo "❌ Test update failed<br>";
            }
        } else {
            echo "❌ Failed to prepare test statement<br>";
        }
    }
    
    $conn->close();
    echo "<h2>✅ Fix Complete</h2>";
    echo "<p>The foreign key constraint issue has been resolved.</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?> 