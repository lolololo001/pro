<?php
require_once 'config/config.php';

echo "Testing database connection...<br>";

try {
    $conn = getDbConnection();
    echo "✅ Database connection successful<br>";
    
    // Check if permission_requests table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'permission_requests'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        echo "✅ permission_requests table exists<br>";
        
        // Check table structure
        $columns = $conn->query("SHOW COLUMNS FROM permission_requests");
        echo "Table columns:<br>";
        while ($col = $columns->fetch_assoc()) {
            echo "- {$col['Field']}<br>";
        }
        
        // Check for data
        $data = $conn->query("SELECT COUNT(*) as count FROM permission_requests");
        $count = $data->fetch_assoc()['count'];
        echo "Total permission requests: $count<br>";
        
        if ($count > 0) {
            $sample = $conn->query("SELECT id, status, created_at FROM permission_requests LIMIT 3");
            echo "Sample data:<br>";
            while ($row = $sample->fetch_assoc()) {
                echo "- ID: {$row['id']}, Status: {$row['status']}, Created: {$row['created_at']}<br>";
            }
        }
        
    } else {
        echo "❌ permission_requests table does not exist<br>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "Test complete.<br>";
?> 