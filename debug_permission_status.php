<?php
/**
 * Debug script to check permission status updates
 */

require_once 'config/config.php';

echo "<h1>Permission Status Debug</h1>";

try {
    $conn = getDbConnection();
    
    // Check table structure
    echo "<h2>1. Table Structure</h2>";
    $columns = $conn->query("SHOW COLUMNS FROM permission_requests");
    if ($columns) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($col = $columns->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check all permission requests
    echo "<h2>2. All Permission Requests</h2>";
    $allRequests = $conn->query("SELECT * FROM permission_requests ORDER BY created_at DESC LIMIT 10");
    if ($allRequests && $allRequests->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Student ID</th><th>Parent ID</th><th>Status</th><th>Response Comment</th><th>Created</th><th>Updated</th></tr>";
        while ($row = $allRequests->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['student_id']}</td>";
            echo "<td>{$row['parent_id']}</td>";
            echo "<td style='background-color: " . ($row['status'] == 'pending' ? '#fff3cd' : ($row['status'] == 'approved' ? '#d4edda' : '#f8d7da')) . "'>{$row['status']}</td>";
            echo "<td>" . (isset($row['response_comment']) ? htmlspecialchars(substr($row['response_comment'], 0, 50)) : 'NULL') . "</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "<td>" . (isset($row['updated_at']) ? $row['updated_at'] : 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No permission requests found.</p>";
    }
    
    // Check status distribution
    echo "<h2>3. Status Distribution</h2>";
    $statusCounts = $conn->query("SELECT status, COUNT(*) as count FROM permission_requests GROUP BY status");
    if ($statusCounts) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Status</th><th>Count</th></tr>";
        while ($row = $statusCounts->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['status']}</td>";
            echo "<td>{$row['count']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check recent updates
    echo "<h2>4. Recent Updates (Last 24 hours)</h2>";
    $recentUpdates = $conn->query("SELECT * FROM permission_requests WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY updated_at DESC");
    if ($recentUpdates && $recentUpdates->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Status</th><th>Response Comment</th><th>Updated At</th></tr>";
        while ($row = $recentUpdates->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['status']}</td>";
            echo "<td>" . (isset($row['response_comment']) ? htmlspecialchars(substr($row['response_comment'], 0, 100)) : 'NULL') . "</td>";
            echo "<td>{$row['updated_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No recent updates found.</p>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h2>5. Test Complete</h2>";
echo "<p>This debug script shows the current state of permission requests in the database.</p>";
?> 