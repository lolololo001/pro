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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Get school_id from session
$school_id = $_SESSION['school_admin_school_id'] ?? 0;
if (!$school_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'School ID not found in session']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['student_id']) || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get parameters
$student_id = intval($_POST['student_id']);
$status = $_POST['status'];

// Validate status
$valid_statuses = ['active', 'inactive', 'graduated', 'shifted'];
if (!in_array($status, $valid_statuses)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

// Get database connection
$conn = getDbConnection();

// Update student status
try {
    $stmt = $conn->prepare("UPDATE students SET status = ? WHERE id = ? AND school_id = ?");
    $stmt->bind_param('sii', $status, $student_id, $school_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Student status updated successfully']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'No student found with the provided ID or status already set to this value']);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();