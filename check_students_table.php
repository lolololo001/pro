<?php
// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once 'config/config.php';

// Use the existing getDbConnection function from config.php

// Get database connection
$conn = getDbConnection();

// Check table structure
echo "<h2>Students Table Structure</h2>";
$result = $conn->query("SHOW CREATE TABLE students");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
} else {
    echo "<p>Error getting table structure: " . $conn->error . "</p>";
}

// Check for any unique constraints
echo "<h2>Indexes on Students Table</h2>";
$result = $conn->query("SHOW INDEX FROM students");
if ($result && $result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Key_name</th><th>Column_name</th><th>Non_unique</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Column_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Non_unique']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error getting indexes: " . $conn->error . "</p>";
}

// Close connection
$conn->close();
?>