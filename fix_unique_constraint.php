<?php
// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once 'config/config.php';

// Get database connection
$conn = getDbConnection();

// Output header
echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Fix Students Table Unique Constraint</title>\n</head>\n<body>\n";
echo "<h1>Fixing Students Table Unique Constraint</h1>\n";

try {
    // First, check if the unique constraint exists
    $result = $conn->query("SHOW CREATE TABLE students");
    $row = $result->fetch_assoc();
    $createTable = $row['Create Table'];
    
    echo "<p>Current table structure:</p>\n";
    echo "<pre>" . htmlspecialchars($createTable) . "</pre>\n";
    
    // Check for any unique constraints
    echo "<h2>Current Indexes on Students Table</h2>\n";
    $result = $conn->query("SHOW INDEX FROM students");
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'>\n";
        echo "<tr><th>Key_name</th><th>Column_name</th><th>Non_unique</th></tr>\n";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>\n";
            echo "<td>" . htmlspecialchars($row['Key_name']) . "</td>\n";
            echo "<td>" . htmlspecialchars($row['Column_name']) . "</td>\n";
            echo "<td>" . htmlspecialchars($row['Non_unique']) . "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p>Error getting indexes: " . $conn->error . "</p>\n";
    }
    
    // Check if there's a unique constraint on school_id and reg_number
    if (strpos($createTable, 'UNIQUE KEY `school_id` (`school_id`,`reg_number`)') !== false) {
        echo "<p>Found unique constraint on school_id and reg_number. Dropping constraint...</p>\n";
        
        // Drop the unique constraint
        if ($conn->query("ALTER TABLE students DROP INDEX school_id")) {
            echo "<p>Successfully dropped the unique constraint.</p>\n";
        } else {
            echo "<p>Error dropping constraint: " . $conn->error . "</p>\n";
        }
    } else if (strpos($createTable, 'UNIQUE KEY `school_id_reg_number`') !== false) {
        echo "<p>Found unique constraint with name 'school_id_reg_number'. Dropping constraint...</p>\n";
        
        // Drop the unique constraint
        if ($conn->query("ALTER TABLE students DROP INDEX school_id_reg_number")) {
            echo "<p>Successfully dropped the unique constraint.</p>\n";
        } else {
            echo "<p>Error dropping constraint: " . $conn->error . "</p>\n";
        }
    } else {
        echo "<p>No problematic unique constraint found on school_id and reg_number.</p>\n";
    }
    
    // Now check if we need to add a new constraint
    $result = $conn->query("SHOW INDEX FROM students WHERE Key_name = 'school_id_reg_number'");
    if ($result->num_rows == 0) {
        echo "<p>Adding new unique constraint that properly handles NULL values...</p>\n";
        
        // In MySQL, NULL values are considered distinct in unique indexes
        // This means multiple NULL values can exist in a column with a unique constraint
        if ($conn->query("ALTER TABLE students ADD UNIQUE INDEX school_id_reg_number (school_id, reg_number)")) {
            echo "<p>Successfully added new unique constraint.</p>\n";
        } else {
            echo "<p>Error adding new constraint: " . $conn->error . "</p>\n";
        }
    } else {
        echo "<p>Unique constraint 'school_id_reg_number' already exists.</p>\n";
    }
    
    // Show the updated table structure
    $result = $conn->query("SHOW CREATE TABLE students");
    $row = $result->fetch_assoc();
    echo "<p>Updated table structure:</p>\n";
    echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>\n";
    
    // Show updated indexes
    echo "<h2>Updated Indexes on Students Table</h2>\n";
    $result = $conn->query("SHOW INDEX FROM students");
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'>\n";
        echo "<tr><th>Key_name</th><th>Column_name</th><th>Non_unique</th></tr>\n";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>\n";
            echo "<td>" . htmlspecialchars($row['Key_name']) . "</td>\n";
            echo "<td>" . htmlspecialchars($row['Column_name']) . "</td>\n";
            echo "<td>" . htmlspecialchars($row['Non_unique']) . "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    echo "<p>Table fix completed successfully!</p>\n";
    echo "<p><a href='school-admin/students.php'>Return to Students Page</a></p>\n";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
}

// Close connection
$conn->close();

// Output footer
echo "</body>\n</html>";
?>