<?php
// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once 'config/config.php';

// Get database connection
$conn = getDbConnection();

// Check if teachers table exists
$result = $conn->query("SHOW TABLES LIKE 'teachers'");
if ($result->num_rows > 0) {
    echo "Teachers table exists.\n";
    
    // Check table structure
    $result = $conn->query("DESCRIBE teachers");
    echo "\nTeachers table structure:\n";
    echo "-------------------------\n";
    while ($row = $result->fetch_assoc()) {
        echo "Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}, Default: {$row['Default']}\n";
    }
    
    // Check if department_id column exists
    $result = $conn->query("SHOW COLUMNS FROM teachers LIKE 'department_id'");
    if ($result->num_rows > 0) {
        echo "\ndepartment_id column exists in teachers table.\n";
    } else {
        echo "\ndepartment_id column DOES NOT exist in teachers table.\n";
    }
} else {
    echo "Teachers table does not exist.\n";
}

// Check if departments table exists
$result = $conn->query("SHOW TABLES LIKE 'departments'");
if ($result->num_rows > 0) {
    echo "\nDepartments table exists.\n";
    
    // Check table structure
    $result = $conn->query("DESCRIBE departments");
    echo "\nDepartments table structure:\n";
    echo "---------------------------\n";
    while ($row = $result->fetch_assoc()) {
        echo "Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}, Default: {$row['Default']}\n";
    }
} else {
    echo "\nDepartments table does not exist.\n";
}

$conn->close();
?>