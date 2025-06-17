<?php
require_once '../config/config.php';

session_start();
if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get parent information
$parentId = $_SESSION['parent_id'];
$parentName = isset($_SESSION['parent_name']) ? $_SESSION['parent_name'] : 'Parent User';
$parentEmail = isset($_SESSION['parent_email']) ? $_SESSION['parent_email'] : '';

$error = '';
$success = '';
$feedbackError = '';
$feedbackSuccess = '';

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $feedbackText = trim($_POST['message'] ?? '');
    $conn = null;
    
    if (empty($feedbackText)) {
        $feedbackError = 'Please enter your feedback.';
    } else {
        try {
            $conn = getDbConnection();
            
            // Insert feedback with school_id subquery
            $sql = "INSERT INTO parent_feedback (parent_id, message, school_id) 
                   SELECT ?, ?, s.school_id 
                   FROM students s 
                   INNER JOIN student_parent sp ON s.id = sp.student_id 
                   WHERE sp.parent_id = ? 
                   LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }
            
            $stmt->bind_param('isi', $parentId, $feedbackText, $parentId);
            if (!$stmt->execute()) {
                throw new Exception('Failed to submit feedback: ' . $stmt->error);
            }
            
            $feedbackSuccess = 'Thank you! Your feedback has been submitted successfully.';
            $stmt->close();
            
        } catch (Exception $e) {
            $feedbackError = $e->getMessage();
            error_log('Feedback submission error: ' . $e->getMessage());
        }
        
        if ($conn) {
            $conn->close();
        }
    }
}

try {
    $conn = getDbConnection();
    $children = [];
    
    // Get students data
    $sql = "SELECT sp.student_id, sp.is_primary, 
            s.first_name, s.last_name, s.admission_number, 
            s.registration_number, s.class_name, s.grade_level
            FROM student_parent sp
            JOIN students s ON sp.student_id = s.id
            WHERE sp.parent_id = ?
            ORDER BY sp.is_primary DESC, s.first_name ASC";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $parentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $children[] = $row;
        }
        $stmt->close();
    }
    
    $conn->close();
    
} catch (Exception $e) {
    $error = 'Error loading children data: ' . $e->getMessage();
}
?>
