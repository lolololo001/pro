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
        $subject = trim($_POST['subject']);
        $category = trim($_POST['category'] ?? ($_POST['feedback_type'] ?? ''));
        
        if (empty($message)) {
            throw new Exception('Please enter your feedback.');
        }

        // Perform sentiment analysis using Python script
        $escapedMessage = escapeshellarg($message);
        $pythonScript = realpath(__DIR__ . '/../python/sentiment_analysis.py');
        $command = "python \"$pythonScript\" $escapedMessage 2>&1";
        $result = shell_exec($command);
        
        // Log the Python script execution
        error_log("Python command: " . $command);
        error_log("Python output: " . $result);
        
        $analysis = json_decode($result, true);
        
        if (!$analysis) {
            throw new Exception('Error analyzing feedback. Python output: ' . $result);
        }
        
        $conn = getDbConnection();
        
        // Get the school_id using a subquery in the INSERT statement
        $sql = "INSERT INTO parent_feedback SET 
                parent_id = ?, 
                subject = ?, 
                message = ?, 
                sentiment_score = ?,
                sentiment_label = ?,
                category = ?,
                suggestion = ?,
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
        
        $stmt->bind_param(
            'issdssss',
            $parentId,
            $subject,
            $message,
            $analysis['sentiment_score'],
            $analysis['sentiment_label'],
            $category,
            $analysis['suggestion'],
            $parentId
        );
        
        if ($stmt->execute()) {
            $feedback_id = $conn->insert_id;

            // Trigger admin notification
            require_once '../includes/admin_notification_triggers.php';
            triggerFeedbackNotification($parentId, $feedback_id, $message);

            $response['success'] = true;
            $response['message'] = $analysis['suggestion'];
            $response['sentiment'] = $analysis['sentiment_label'];
            $response['category'] = $analysis['category'];
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
