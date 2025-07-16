<?php
require_once 'config/config.php';

$conn = getDbConnection();

echo "<h1>Fix Permission Status Values</h1>";

// First, let's see what we have
echo "<h2>Current Status Values:</h2>";
$check_query = "SELECT DISTINCT status, COUNT(*) as count FROM permission_requests GROUP BY status";
$check_result = $conn->query($check_query);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Status</th><th>Count</th></tr>";

while ($row = $check_result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['count'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Fix common issues
echo "<h2>Fixing Status Values...</h2>";

// Update empty or NULL status to 'pending'
$update_null = "UPDATE permission_requests SET status = 'pending' WHERE status IS NULL OR status = ''";
$conn->query($update_null);
echo "<p>Updated NULL/empty status values to 'pending'</p>";

// Update common variations
$update_approved = "UPDATE permission_requests SET status = 'approved' WHERE LOWER(TRIM(status)) IN ('approve', 'accepted', 'accept')";
$conn->query($update_approved);
echo "<p>Updated approve/accepted variations to 'approved'</p>";

$update_rejected = "UPDATE permission_requests SET status = 'rejected' WHERE LOWER(TRIM(status)) IN ('reject', 'denied', 'deny')";
$conn->query($update_rejected);
echo "<p>Updated reject/denied variations to 'rejected'</p>";

// Show final results
echo "<h2>Final Status Values:</h2>";
$final_query = "SELECT DISTINCT status, COUNT(*) as count FROM permission_requests GROUP BY status";
$final_result = $conn->query($final_query);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Status</th><th>Count</th></tr>";

while ($row = $final_result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['status'] ?? 'NULL') . "</td>";
    echo "<td>" . $row['count'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Fix Complete!</h2>";
echo "<p>All status values have been standardized to: pending, approved, rejected</p>";

$conn->close();
?> 