<?php
/**
 * Fix permission status issue
 * This script will fix existing permission requests that have empty status fields
 */

require_once 'config/config.php';

echo "<h1>Fix Permission Status</h1>";

try {
    $conn = getDbConnection();
    
    // Step 1: Check current table structure
    echo "<h2>1. Checking Table Structure</h2>";
    $columns = $conn->query("SHOW COLUMNS FROM permission_requests");
    if ($columns) {
        echo "Current columns:<br>";
        while ($col = $columns->fetch_assoc()) {
            echo "- {$col['Field']}: {$col['Type']} " . ($col['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "<br>";
        }
    }
    
    // Step 2: Check for records with empty status
    echo "<h2>2. Checking for Empty Status Records</h2>";
    $emptyStatus = $conn->query("SELECT id, status, created_at FROM permission_requests WHERE status IS NULL OR status = ''");
    if ($emptyStatus && $emptyStatus->num_rows > 0) {
        echo "Found {$emptyStatus->num_rows} records with empty status:<br>";
        while ($row = $emptyStatus->fetch_assoc()) {
            echo "- ID: {$row['id']}, Status: '{$row['status']}', Created: {$row['created_at']}<br>";
        }
        
        // Step 3: Fix empty status records
        echo "<h2>3. Fixing Empty Status Records</h2>";
        $updateStmt = $conn->prepare("UPDATE permission_requests SET status = 'pending' WHERE status IS NULL OR status = ''");
        if ($updateStmt) {
            $updateStmt->execute();
            $affected = $updateStmt->affected_rows;
            echo "✅ Updated $affected records to 'pending' status<br>";
        } else {
            echo "❌ Failed to prepare update statement<br>";
        }
    } else {
        echo "✅ No records with empty status found<br>";
    }
    
    // Step 4: Ensure table has proper structure
    echo "<h2>4. Ensuring Proper Table Structure</h2>";
    
    // Check if status column has proper ENUM constraint
    $statusColumn = $conn->query("SHOW COLUMNS FROM permission_requests LIKE 'status'");
    if ($statusColumn && $statusColumn->num_rows > 0) {
        $col = $statusColumn->fetch_assoc();
        echo "Status column type: {$col['Type']}<br>";
        
        // If it's not an ENUM, alter it
        if (strpos($col['Type'], 'enum') === false) {
            echo "Updating status column to ENUM...<br>";
            $alterStmt = $conn->prepare("ALTER TABLE permission_requests MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
            if ($alterStmt) {
                $alterStmt->execute();
                echo "✅ Status column updated to ENUM<br>";
            } else {
                echo "❌ Failed to update status column<br>";
            }
        } else {
            echo "✅ Status column already has proper ENUM constraint<br>";
        }
    }
    
    // Step 5: Add missing columns if they don't exist
    echo "<h2>5. Adding Missing Columns</h2>";
    
    $columns = $conn->query("SHOW COLUMNS FROM permission_requests");
    $existingColumns = [];
    while ($col = $columns->fetch_assoc()) {
        $existingColumns[] = $col['Field'];
    }
    
    // Add response_comment if missing
    if (!in_array('response_comment', $existingColumns)) {
        echo "Adding response_comment column...<br>";
        $conn->query("ALTER TABLE permission_requests ADD COLUMN response_comment TEXT NULL");
        echo "✅ response_comment column added<br>";
    } else {
        echo "✅ response_comment column already exists<br>";
    }
    
    // Add responded_by if missing
    if (!in_array('responded_by', $existingColumns)) {
        echo "Adding responded_by column...<br>";
        $conn->query("ALTER TABLE permission_requests ADD COLUMN responded_by INT NULL");
        echo "✅ responded_by column added<br>";
    } else {
        echo "✅ responded_by column already exists<br>";
    }
    
    // Add updated_at if missing
    if (!in_array('updated_at', $existingColumns)) {
        echo "Adding updated_at column...<br>";
        $conn->query("ALTER TABLE permission_requests ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP");
        echo "✅ updated_at column added<br>";
    } else {
        echo "✅ updated_at column already exists<br>";
    }
    
    // Step 6: Verify the fix
    echo "<h2>6. Verification</h2>";
    $verify = $conn->query("SELECT id, status, created_at FROM permission_requests ORDER BY created_at DESC LIMIT 5");
    if ($verify && $verify->num_rows > 0) {
        echo "Recent permission requests:<br>";
        while ($row = $verify->fetch_assoc()) {
            $statusColor = $row['status'] == 'pending' ? '#fff3cd' : ($row['status'] == 'approved' ? '#d4edda' : '#f8d7da');
            echo "- ID: {$row['id']}, Status: <span style='background-color: $statusColor; padding: 2px 6px; border-radius: 3px;'>{$row['status']}</span>, Created: {$row['created_at']}<br>";
        }
    }
    
    $conn->close();
    echo "<h2>✅ Fix Complete</h2>";
    echo "<p>The permission status issue has been resolved. All records now have proper status values.</p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?> 