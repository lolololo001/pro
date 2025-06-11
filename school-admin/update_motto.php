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

// Check if motto column exists in schools table
$column_exists = false;
try {
    $check_column = $conn->query("SHOW COLUMNS FROM schools LIKE 'motto'");
    $column_exists = ($check_column->num_rows > 0);
} catch (Exception $e) {
    error_log("Error checking motto column: " . $e->getMessage());
}

// Add motto column if it doesn't exist
if (!$column_exists) {
    try {
        $conn->query("ALTER TABLE schools ADD COLUMN motto TEXT");
        error_log("Added motto column to schools table");
    } catch (Exception $e) {
        $_SESSION['logo_error'] = 'Failed to update database structure: ' . $e->getMessage();
        header('Location: logo.php');
        exit;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['motto'])) {
    $motto = trim($_POST['motto']);
    
    // Update school motto
    $stmt = $conn->prepare("UPDATE schools SET motto = ? WHERE id = ?");
    $stmt->bind_param('si', $motto, $school_id);
    
    if ($stmt->execute()) {
        $_SESSION['logo_success'] = 'School motto updated successfully!';
    } else {
        $_SESSION['logo_error'] = 'Failed to update school motto: ' . $conn->error;
    }
    
    $stmt->close();
    header('Location: logo.php');
    exit;
}

$conn->close();
header('Location: logo.php');
exit;