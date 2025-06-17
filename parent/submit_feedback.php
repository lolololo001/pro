<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/config.php';

session_start();
if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $parentId = $_SESSION['parent_id'];
        $message = trim($_POST['message']);
        
        if (empty($message)) {
            throw new Exception('Please enter your feedback.');
        }
        
        $conn = getDbConnection();
        
        // Get the school_id using a subquery in the INSERT statement
        $sql = "INSERT INTO parent_feedback SET 
                parent_id = ?, 
                message = ?, 
                school_id = (
                    SELECT s.school_id 
                    FROM students s 
                    INNER JOIN student_parent sp ON s.id = sp.student_id 
                    WHERE sp.parent_id = ? 
                    LIMIT 1
                )";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param('isi', $parentId, $message, $parentId);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Thank you! Your feedback has been submitted successfully.';
        } else {
            throw new Exception("Failed to submit feedback: " . $stmt->error);
        }
        
        $stmt->close();
        $conn->close();
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
