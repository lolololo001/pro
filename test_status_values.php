<?php
require_once 'config/config.php';

$conn = getDbConnection();

echo "<h1>Status Value Test</h1>";

// Get all unique status values
$query = "SELECT DISTINCT status FROM permission_requests ORDER BY status";
$result = $conn->query($query);

echo "<h2>All Status Values in Database:</h2>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Original Status</th><th>After strtolower(trim())</th><th>Is Empty?</th><th>Length</th></tr>";

while ($row = $result->fetch_assoc()) {
    $original = $row['status'];
    $processed = strtolower(trim($original ?? ''));
    $isEmpty = empty($processed) ? 'Yes' : 'No';
    $length = strlen($processed);
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($original ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($processed) . "</td>";
    echo "<td>" . $isEmpty . "</td>";
    echo "<td>" . $length . "</td>";
    echo "</tr>";
}

echo "</table>";

// Test the exact logic from student_info.php
echo "<h2>Testing Exact Logic from student_info.php:</h2>";
$test_query = "SELECT id, status FROM permission_requests ORDER BY created_at DESC LIMIT 10";
$test_result = $conn->query($test_query);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Original Status</th><th>Processed Status</th><th>Final Display</th></tr>";

while ($row = $test_result->fetch_assoc()) {
    $original_status = $row['status'];
    $status = strtolower(trim($original_status ?? ''));
    
    $displayStatus = 'Unknown (' . htmlspecialchars($original_status ?? 'NULL') . ')';
    
    if ($status === 'pending') {
        $displayStatus = 'Pending';
    } elseif (in_array($status, ['approved', 'approve', 'accepted', 'accept'])) {
        $displayStatus = 'Approved';
    } elseif (in_array($status, ['rejected', 'reject', 'denied', 'deny'])) {
        $displayStatus = 'Rejected';
    } elseif (empty($status) || $status === 'null') {
        $displayStatus = 'Pending (No Status)';
    }
    
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($original_status ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($status) . "</td>";
    echo "<td>" . $displayStatus . "</td>";
    echo "</tr>";
}

echo "</table>";

$conn->close();
?> 