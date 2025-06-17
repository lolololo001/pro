<?php
// Create minimal test file to verify the feedback submission
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../config/config.php';

session_start();
if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit;
}

$parentId = $_SESSION['parent_id'];
$feedbackError = '';
$feedbackSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $feedbackText = trim($_POST['message']);
    
    try {
        $conn = getDbConnection();
        $sql = "INSERT INTO parent_feedback (parent_id, message, school_id) 
                SELECT ?, ?, s.school_id 
                FROM students s 
                INNER JOIN student_parent sp ON s.id = sp.student_id 
                WHERE sp.parent_id = ? LIMIT 1";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        
        $stmt->bind_param('isi', $parentId, $feedbackText, $parentId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
            exit;
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
?>
