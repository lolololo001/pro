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

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['parent_error'] = 'Invalid request method.';
    header('Location: parents.php');
    exit;
}

// Validate form data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

if (empty($name)) {
    $_SESSION['parent_error'] = 'Parent name is required.';
    header('Location: parents.php');
    exit;
}

if (empty($email)) {
    $_SESSION['parent_error'] = 'Email address is required.';
    header('Location: parents.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['parent_error'] = 'Please enter a valid email address.';
    header('Location: parents.php');
    exit;
}

// Get database connection
$conn = getDbConnection();

// Check if email already exists for this school
try {
    $stmt = $conn->prepare('SELECT id FROM parents WHERE email = ? AND school_id = ?');
    $stmt->bind_param('si', $email, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['parent_error'] = 'A parent with this email already exists.';
        header('Location: parents.php');
        exit;
    }
    
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['parent_error'] = 'Database error: ' . $e->getMessage();
    header('Location: parents.php');
    exit;
}

// Insert new parent
try {
    $stmt = $conn->prepare('INSERT INTO parents (school_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('issss', $school_id, $name, $email, $phone, $address);
    
    if ($stmt->execute()) {
        $_SESSION['parent_success'] = 'Parent has been added successfully.';
    } else {
        $_SESSION['parent_error'] = 'Failed to add parent: ' . $stmt->error;
    }
    
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['parent_error'] = 'Database error: ' . $e->getMessage();
}

// Close database connection
$conn->close();

// Redirect back to parents page
header('Location: parents.php');
exit;