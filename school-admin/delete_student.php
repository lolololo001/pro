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

// Check if student ID is provided
if (!isset($_POST['student_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

// Get student ID
$student_id = intval($_POST['student_id']);

// Get database connection
$conn = getDbConnection();

// Delete student record
try {
    // First verify the student belongs to the school
    $check_stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND school_id = ?");
    $check_stmt->bind_param('ii', $student_id, $school_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }
    
    $check_stmt->close();
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete related records first (dependent tables)
        // Delete from student_status_logs
        $log_stmt = $conn->prepare("DELETE FROM student_status_logs WHERE student_id = ?");
        $log_stmt->bind_param('i', $student_id);
        $log_stmt->execute();
        $log_stmt->close();
        
        // Delete from student_parent if exists
        $parent_stmt = $conn->prepare("DELETE FROM student_parent WHERE student_id = ?");
        $parent_stmt->bind_param('i', $student_id);
        $parent_stmt->execute();
        $parent_stmt->close();
        
        // Finally delete the student
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ? AND school_id = ?");
        $stmt->bind_param('ii', $student_id, $school_id);
        
        if ($stmt->execute()) {
            // Commit transaction
            $conn->commit();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Student deleted successfully',
                'data' => [
                    'student_id' => $student_id
                ]
            ]);
        } else {
            // Rollback on failure
            $conn->rollback();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete student']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        // Rollback on exception
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Error deleting student: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the student']);
}

$conn->close();
