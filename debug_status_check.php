<?php
require_once 'config/config.php';

echo "<h1>Debug Permission Status Values</h1>";

try {
    $conn = getDbConnection();
    
    // Check what status values actually exist in the database
    echo "<h2>1. All Status Values in Database</h2>";
    $statusQuery = "SELECT DISTINCT status, COUNT(*) as count FROM permission_requests GROUP BY status";
    $result = $conn->query($statusQuery);
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Status Value</th><th>Count</th><th>Length</th><th>ASCII Values</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $status = $row['status'];
            $count = $row['count'];
            $length = strlen($status);
            $ascii = '';
            for ($i = 0; $i < $length; $i++) {
                $ascii .= ord($status[$i]) . ' ';
            }
            echo "<tr>";
            echo "<td>'" . htmlspecialchars($status) . "'</td>";
            echo "<td>$count</td>";
            echo "<td>$length</td>";
            echo "<td>$ascii</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No permission requests found.";
    }
    
    // Check recent permission requests with their exact status values
    echo "<h2>2. Recent Permission Requests (Last 10)</h2>";
    $recentQuery = "SELECT id, student_id, parent_id, status, request_type, created_at, updated_at 
                    FROM permission_requests 
                    ORDER BY created_at DESC 
                    LIMIT 10";
    $result = $conn->query($recentQuery);
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Student ID</th><th>Parent ID</th><th>Status</th><th>Type</th><th>Created</th><th>Updated</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $statusDisplay = "'" . htmlspecialchars($row['status']) . "' (len:" . strlen($row['status']) . ")";
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['student_id']}</td>";
            echo "<td>{$row['parent_id']}</td>";
            echo "<td>$statusDisplay</td>";
            echo "<td>{$row['request_type']}</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "<td>{$row['updated_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test the exact comparison logic
    echo "<h2>3. Testing Status Comparison Logic</h2>";
    $testQuery = "SELECT status FROM permission_requests LIMIT 5";
    $result = $conn->query($testQuery);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $status = $row['status'];
            $trimmed = trim($status);
            $lowered = strtolower($trimmed);
            
            echo "Original: '" . htmlspecialchars($status) . "' -> ";
            echo "Trimmed: '" . htmlspecialchars($trimmed) . "' -> ";
            echo "Lowered: '" . htmlspecialchars($lowered) . "'<br>";
            
            // Test the exact conditions from the code
            if ($lowered === 'pending') {
                echo "✅ Would show as PENDING<br>";
            } elseif ($lowered === 'approved') {
                echo "✅ Would show as APPROVED<br>";
            } elseif ($lowered === 'rejected') {
                echo "✅ Would show as REJECTED<br>";
            } else {
                echo "❌ Would show as UNKNOWN<br>";
            }
            echo "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
