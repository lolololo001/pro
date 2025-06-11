<?php
// Start session
session_start();

// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once '../config/config.php';

// Check if school admin is logged in
if (!isset($_SESSION['school_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get school_id from session
$school_id = $_SESSION['school_admin_school_id'] ?? 0;
if (!$school_id) {
    die("Error: School ID not found in session. Please log in again.");
}

// Get database connection
$conn = getDbConnection();

// Check if teachers table exists
$result = $conn->query("SHOW TABLES LIKE 'teachers'");
if ($result->num_rows > 0) {
    echo "<p>Teachers table exists.</p>";
    
    // Check if department_id column exists
    $result = $conn->query("SHOW COLUMNS FROM teachers LIKE 'department_id'");
    if ($result->num_rows == 0) {
        // Add department_id column if it doesn't exist
        try {
            $conn->query("ALTER TABLE teachers ADD COLUMN department_id INT NULL, ADD FOREIGN KEY (department_id) REFERENCES departments(dep_id) ON DELETE SET NULL");
            echo "<p style='color:green'>Successfully added department_id column to teachers table.</p>";
        } catch (Exception $e) {
            echo "<p style='color:red'>Error adding department_id column: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>department_id column already exists in teachers table.</p>";
    }
} else {
    echo "<p>Teachers table does not exist. Please visit the teachers page to create it.</p>";
}

// Check if subject column exists
$result = $conn->query("SHOW COLUMNS FROM teachers LIKE 'subject'");
if ($result->num_rows == 0) {
    // Add subject column if it doesn't exist
    try {
        $conn->query("ALTER TABLE teachers ADD COLUMN subject VARCHAR(50) NULL AFTER phone");
        echo "<p style='color:green'>Successfully added subject column to teachers table.</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>Error adding subject column: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>subject column already exists in teachers table.</p>";
}

echo "<p><a href='teachers.php'>Return to Teachers Page</a></p>";

$conn->close();
?>