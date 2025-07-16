<?php
// Debug script to check permission status values
require_once 'config/config.php';

$conn = getDbConnection();

echo "<h1>Permission Status Debug - Detailed Analysis</h1>";

// Check what status values exist in the database
$query = "SELECT DISTINCT status, COUNT(*) as count FROM permission_requests GROUP BY status";
$result = $conn->query($query);

echo "<h2>1. Permission Status Values in Database:</h2>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Status</th><th>Count</th><th>Length</th><th>Hex</th></tr>";

while ($row = $result->fetch_assoc()) {
    $status = $row['status'];
    $length = strlen($status);
    $hex = bin2hex($status);
    echo "<tr>";
    echo "<td>" . htmlspecialchars($status) . "</td>";
    echo "<td>" . $row['count'] . "</td>";
    echo "<td>" . $length . "</td>";
    echo "<td>" . $hex . "</td>";
    echo "</tr>";
}

echo "</table>";

// Check for NULL and empty values
echo "<h2>2. NULL and Empty Status Values:</h2>";
$null_query = "SELECT COUNT(*) as null_count FROM permission_requests WHERE status IS NULL";
$null_result = $conn->query($null_query);
$null_count = $null_result->fetch_assoc()['null_count'];

$empty_query = "SELECT COUNT(*) as empty_count FROM permission_requests WHERE status = ''";
$empty_result = $conn->query($empty_query);
$empty_count = $empty_result->fetch_assoc()['empty_count'];

echo "<p>NULL status values: " . $null_count . "</p>";
echo "<p>Empty string status values: " . $empty_count . "</p>";

// Show sample records with detailed status info
echo "<h2>3. Sample Permission Records with Status Details:</h2>";
$sample_query = "SELECT id, status, request_type, created_at, 
                        CASE 
                            WHEN status IS NULL THEN 'NULL'
                            WHEN status = '' THEN 'EMPTY_STRING'
                            ELSE status
                        END as status_type,
                        LENGTH(status) as status_length,
                        HEX(status) as status_hex
                 FROM permission_requests 
                 ORDER BY created_at DESC 
                 LIMIT 15";
$sample_result = $conn->query($sample_query);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Status</th><th>Status Type</th><th>Length</th><th>Hex</th><th>Request Type</th><th>Created At</th></tr>";

while ($row = $sample_result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['status_type'] . "</td>";
    echo "<td>" . $row['status_length'] . "</td>";
    echo "<td>" . $row['status_hex'] . "</td>";
    echo "<td>" . htmlspecialchars($row['request_type']) . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Check table structure
echo "<h2>4. Table Structure:</h2>";
$structure_query = "SHOW COLUMNS FROM permission_requests LIKE 'status'";
$structure_result = $conn->query($structure_query);

if ($structure_result && $structure_result->num_rows > 0) {
    $column = $structure_result->fetch_assoc();
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    echo "<tr>";
    echo "<td>" . $column['Field'] . "</td>";
    echo "<td>" . $column['Type'] . "</td>";
    echo "<td>" . $column['Null'] . "</td>";
    echo "<td>" . $column['Key'] . "</td>";
    echo "<td>" . $column['Default'] . "</td>";
    echo "<td>" . $column['Extra'] . "</td>";
    echo "</tr>";
    echo "</table>";
}

// Test the status processing logic
echo "<h2>5. Status Processing Test:</h2>";
$test_query = "SELECT DISTINCT status FROM permission_requests LIMIT 10";
$test_result = $conn->query($test_query);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Original Status</th><th>After strtolower(trim())</th><th>Processed Result</th></tr>";

while ($row = $test_result->fetch_assoc()) {
    $original = $row['status'];
    $processed = strtolower(trim($original));
    
    $result_status = 'Unknown';
    if ($processed === 'pending') {
        $result_status = 'Pending';
    } elseif (in_array($processed, ['approved', 'approve', 'accepted', 'accept'])) {
        $result_status = 'Approved';
    } elseif (in_array($processed, ['rejected', 'reject', 'denied', 'deny'])) {
        $result_status = 'Rejected';
    }
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($original ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($processed ?? 'NULL') . "</td>";
    echo "<td>" . $result_status . "</td>";
    echo "</tr>";
}

echo "</table>";

$conn->close();
?> 