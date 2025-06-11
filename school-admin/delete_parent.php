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

// Check if parent ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['parent_error'] = 'Parent ID is required.';
    header('Location: parents.php');
    exit;
}

$parent_id = intval($_GET['id']);

// Get database connection
$conn = getDbConnection();

// Check if parent exists and belongs to this school
try {
    $stmt = $conn->prepare('SELECT id FROM parents WHERE id = ? AND school_id = ?');
    $stmt->bind_param('ii', $parent_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['parent_error'] = 'Parent not found or you do not have permission to delete this parent.';
        header('Location: parents.php');
        exit;
    }
    
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['parent_error'] = 'Database error: ' . $e->getMessage();
    header('Location: parents.php');
    exit;
}

// Delete parent
try {
    $stmt = $conn->prepare('DELETE FROM parents WHERE id = ? AND school_id = ?');
    $stmt->bind_param('ii', $parent_id, $school_id);
    
    if ($stmt->execute()) {
        $_SESSION['parent_success'] = 'Parent has been deleted successfully.';
    } else {
        $_SESSION['parent_error'] = 'Failed to delete parent: ' . $stmt->error;
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