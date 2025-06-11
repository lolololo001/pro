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

// Initialize response
$response = [
    'success' => false,
    'message' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $bursar_name = trim($_POST['bursar_name'] ?? '');
    $bursar_email = trim($_POST['bursar_email'] ?? '');
    $bursar_phone = trim($_POST['bursar_phone'] ?? '');
    
    if (empty($bursar_name) || empty($bursar_email) || empty($bursar_phone)) {
        $_SESSION['bursar_error'] = 'All fields are required.';
        header('Location: bursars.php');
        exit;
    }

    // Validate email format
    if (!filter_var($bursar_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['bursar_error'] = 'Please enter a valid email address.';
        header('Location: bursars.php');
        exit;
    }
    
    try {
        // Get database connection
        $conn = getDbConnection();
        
        // Check if bursars table exists
        $result = $conn->query("SHOW TABLES LIKE 'bursars'");
        if ($result->num_rows == 0) {
            // Create bursars table if it doesn't exist
            $conn->query("CREATE TABLE IF NOT EXISTS bursars (
                id INT AUTO_INCREMENT PRIMARY KEY,
                school_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
            )");
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM bursars WHERE email = ? AND school_id = ?");
        $stmt->bind_param('si', $bursar_email, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['bursar_error'] = 'A bursar with this email already exists.';
            header('Location: bursars.php');
            exit;
        }
        
        // Insert new bursar
        $stmt = $conn->prepare("INSERT INTO bursars (school_id, name, email, phone) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $school_id, $bursar_name, $bursar_email, $bursar_phone);
        
        if ($stmt->execute()) {
            $_SESSION['bursar_success'] = 'Bursar added successfully!';
        } else {
            $_SESSION['bursar_error'] = 'Failed to add bursar: ' . $conn->error;
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $_SESSION['bursar_error'] = 'System error: ' . $e->getMessage();
    }
    
    // Redirect back to bursars page
    header('Location: bursars.php');
    exit;
} else {
    // Not a POST request, redirect to bursars page
    header('Location: bursars.php');
    exit;
}