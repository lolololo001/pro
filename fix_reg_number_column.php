<?php
// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once 'config/config.php';

// Get database connection
$conn = getDbConnection();

// Output header
echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Fix Students Table Reg Number Column</title>\n</head>\n<body>\n";
echo "<h1>Fixing Students Table Reg Number Column</h1>\n";

try {
    // First, check the current table structure
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
    
    // Check if reg_number column exists and its current definition
    $result = $conn->query("SHOW COLUMNS FROM students LIKE 'reg_number'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<p>Current reg_number column definition: " . htmlspecialchars($row['Type']) . ", Null: " . htmlspecialchars($row['Null']) . "</p>\n";
        
        // Modify reg_number column to explicitly allow NULL values
        echo "<p>Modifying reg_number column to allow NULL values...</p>\n";
        if ($conn->query("ALTER TABLE students MODIFY reg_number VARCHAR(20) NULL")) {
            echo "<p>Successfully modified reg_number column to allow NULL values.</p>\n";
        } else {
            echo "<p>Error modifying reg_number column: " . $conn->error . "</p>\n";
        }
    } else {
        echo "<p>reg_number column does not exist in students table.</p>\n";
    }
    
    // Check for existing unique constraints
    echo "<p>Checking for existing unique constraints...</p>\n";
    
    // We can't drop the school_id index if it's used in a foreign key constraint
    // Instead, we'll create a new separate unique index for reg_number
    
    // First, check if a separate unique index for reg_number already exists
    $result = $conn->query("SHOW INDEX FROM students WHERE Key_name = 'unique_reg_number_per_school'");
    if ($result->num_rows > 0) {
        echo "<p>Unique constraint 'unique_reg_number_per_school' already exists.</p>\n";
    } else {
        // Create a new unique index specifically for reg_number per school
        echo "<p>Creating a new unique constraint for reg_number per school...</p>\n";
        
        // In MySQL, NULL values are considered distinct in unique indexes
        // This means multiple NULL values can exist in a column with a unique constraint
        if ($conn->query("ALTER TABLE students ADD CONSTRAINT unique_reg_number_per_school UNIQUE (school_id, reg_number)")) {
            echo "<p>Successfully added new unique constraint.</p>\n";
        } else {
            // If there's an error, it might be because of duplicate values
            echo "<p>Error adding new constraint: " . $conn->error . "</p>\n";
            
            // Check for duplicate reg_numbers within the same school
            echo "<p>Checking for duplicate reg_numbers within schools...</p>\n";
            $dup_result = $conn->query("SELECT school_id, reg_number, COUNT(*) as count FROM students 
                                      WHERE reg_number IS NOT NULL 
                                      GROUP BY school_id, reg_number 
                                      HAVING count > 1");
            
            if ($dup_result->num_rows > 0) {
                echo "<p>Found duplicate reg_numbers within schools:</p>\n";
                echo "<table border='1'>\n";
                echo "<tr><th>School ID</th><th>Reg Number</th><th>Count</th></tr>\n";
                while ($row = $dup_result->fetch_assoc()) {
                    echo "<tr>\n";
                    echo "<td>" . htmlspecialchars($row['school_id']) . "</td>\n";
                    echo "<td>" . htmlspecialchars($row['reg_number']) . "</td>\n";
                    echo "<td>" . htmlspecialchars($row['count']) . "</td>\n";
                    echo "</tr>\n";
                }
                echo "</table>\n";
                
                echo "<p>Fixing duplicate reg_numbers...</p>\n";
                
                // Get all duplicates and update them with new unique reg_numbers
                $dup_query = "SELECT id, school_id, reg_number FROM students 
                             WHERE (school_id, reg_number) IN (
                                 SELECT school_id, reg_number FROM students 
                                 WHERE reg_number IS NOT NULL 
                                 GROUP BY school_id, reg_number 
                                 HAVING COUNT(*) > 1
                             ) ORDER BY school_id, reg_number, id";
                             
                $dup_result = $conn->query($dup_query);
                
                $current_school = 0;
                $current_reg = '';
                $counter = 1;
                
                while ($row = $dup_result->fetch_assoc()) {
                    // If this is a new school/reg_number combination, reset counter
                    if ($row['school_id'] != $current_school || $row['reg_number'] != $current_reg) {
                        $current_school = $row['school_id'];
                        $current_reg = $row['reg_number'];
                        $counter = 1;
                    }
                    
                    // Skip the first occurrence (keep it as is)
                    if ($counter > 1) {
                        // Generate a new unique reg_number by appending a suffix
                        $new_reg = $row['reg_number'] . '-' . $counter;
                        
                        // Update the record with the new reg_number
                        $update_stmt = $conn->prepare("UPDATE students SET reg_number = ? WHERE id = ?");
                        $update_stmt->bind_param('si', $new_reg, $row['id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                        
                        echo "<p>Updated student ID " . $row['id'] . " with new reg_number: " . $new_reg . "</p>\n";
                    }
                    
                    $counter++;
                }
                
                // Try adding the constraint again
                if ($conn->query("ALTER TABLE students ADD CONSTRAINT unique_reg_number_per_school UNIQUE (school_id, reg_number)")) {
                    echo "<p>Successfully added new unique constraint after fixing duplicates.</p>\n";
                } else {
                    echo "<p>Error adding constraint after fixing duplicates: " . $conn->error . "</p>\n";
                }
            }
        }
    }
    
    // No need to add another constraint here as we've already handled it above
    
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