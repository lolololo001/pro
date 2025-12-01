<?php
header('Content-Type: application/json');
require_once '../config/config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['parent_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get form data
    $feedbackText = trim($_POST['message'] ?? '');
    $feedbackType = trim($_POST['feedback_type'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $parentId = $_SESSION['parent_id'];
    
    // Validate input
    if (empty($feedbackText)) {
        throw new Exception('Please enter your feedback.');
    }
    if (empty($feedbackType)) {
        throw new Exception('Please select a feedback type.');
    }
    if (empty($subject)) {
        throw new Exception('Please enter a subject for your feedback.');
    }
    
    // Get parent information
    $conn = getDbConnection();
    $parentStmt = $conn->prepare("SELECT email, CONCAT(first_name, ' ', last_name) as full_name FROM parents WHERE id = ?");
    $parentStmt->bind_param('i', $parentId);
    $parentStmt->execute();
    $parentResult = $parentStmt->get_result();
    
    if ($parentResult->num_rows === 0) {
        throw new Exception('Parent information not found.');
    }
    
    $parentData = $parentResult->fetch_assoc();
    $parentEmail = $parentData['email'];
    $parentName = $parentData['full_name'];
    $parentStmt->close();
    
    // Get school_id
    $schoolQuery = "SELECT s.school_id 
                  FROM students s 
                  INNER JOIN student_parent sp ON s.id = sp.student_id 
                  WHERE sp.parent_id = ? 
                  LIMIT 1";
    $stmt = $conn->prepare($schoolQuery);
    $stmt->bind_param('i', $parentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('No school found for this parent.');
    }
    
    $schoolData = $result->fetch_assoc();
    $schoolId = $schoolData['school_id'];
    $stmt->close();
    
    // Perform sentiment analysis
    $escapedFeedback = escapeshellarg($feedbackText);
    $pythonScript = escapeshellcmd(__DIR__ . '/../python/sentiment_analysis_fallback.py');
    $command = "python $pythonScript $escapedFeedback 2>&1";
    
    // Log the command for debugging
    error_log("Sentiment analysis command: " . $command);
    
    // Set a timeout to prevent hanging
    $output = shell_exec("timeout 30 $command 2>&1");
    
    // Log the output for debugging
    error_log("Sentiment analysis output: " . $output);
    
    // If timeout occurred, use fallback
    if ($output === null || empty($output)) {
        error_log("Sentiment analysis timed out or failed, using fallback");
        $sentimentResult = null;
    } else {
        $sentimentResult = json_decode($output, true);
    }
    
    // Fallback if sentiment analysis fails
    if (!$sentimentResult || !isset($sentimentResult['sentiment_score'])) {
        $sentimentScore = 0.5; // neutral
        $sentimentLabel = 'Neutral';
        $suggestion = 'Thank you for your feedback. We will review and address your concerns.';
        $confidence = 0.5;
    } else {
        $sentimentScore = $sentimentResult['sentiment_score'];
        $sentimentLabel = $sentimentResult['sentiment_label'];
        $suggestion = $sentimentResult['suggestion'];
        $confidence = $sentimentResult['confidence'] ?? 0.5;
    }
    
    // Insert feedback into database
    $conn->begin_transaction();
    
    // Check if confidence_score column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM parent_feedback LIKE 'confidence_score'");
    $hasConfidenceColumn = $checkColumn && $checkColumn->num_rows > 0;
    
    // Check if category column exists (some databases use category instead of feedback_type)
    $checkCategoryColumn = $conn->query("SHOW COLUMNS FROM parent_feedback LIKE 'category'");
    $hasCategoryColumn = $checkCategoryColumn && $checkCategoryColumn->num_rows > 0;
    
    if ($hasConfidenceColumn && $hasCategoryColumn) {
        $insertSQL = "INSERT INTO parent_feedback (parent_id, school_id, subject, sentiment_score, sentiment_label, message, suggestion, category, confidence_score) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertSQL);
        $stmt->bind_param('iisdssssd', $parentId, $schoolId, $subject, $sentimentScore, $sentimentLabel, $feedbackText, $suggestion, $feedbackType, $confidence);
    } elseif ($hasConfidenceColumn) {
        $insertSQL = "INSERT INTO parent_feedback (parent_id, school_id, subject, sentiment_score, sentiment_label, message, suggestion, feedback_type, confidence_score) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertSQL);
        $stmt->bind_param('iisdssssd', $parentId, $schoolId, $subject, $sentimentScore, $sentimentLabel, $feedbackText, $suggestion, $feedbackType, $confidence);
    } elseif ($hasCategoryColumn) {
        $insertSQL = "INSERT INTO parent_feedback (parent_id, school_id, subject, sentiment_score, sentiment_label, message, suggestion, category) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertSQL);
        $stmt->bind_param('iisdssss', $parentId, $schoolId, $subject, $sentimentScore, $sentimentLabel, $feedbackText, $suggestion, $feedbackType);
    } else {
        $insertSQL = "INSERT INTO parent_feedback (parent_id, school_id, subject, sentiment_score, sentiment_label, message, suggestion, feedback_type) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertSQL);
        $stmt->bind_param('iisdssss', $parentId, $schoolId, $subject, $sentimentScore, $sentimentLabel, $feedbackText, $suggestion, $feedbackType);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to submit feedback: ' . $stmt->error);
    }
    
    $feedbackId = $conn->insert_id;
    $stmt->close();
    
    // Send email notification
    require_once '../includes/email_helper_new.php';
    $emailResult = sendFeedbackConfirmationEmail(
        $parentEmail,
        $parentName,
        $feedbackType,
        $subject,
        $feedbackText
    );
    
    $conn->commit();
    $conn->close();
    
    // Prepare response data
    $responseData = [
        'success' => true,
        'message' => 'Feedback submitted successfully!',
        'sentiment_data' => [
            'sentiment_label' => $sentimentLabel,
            'sentiment_score' => $sentimentScore,
            'confidence' => $confidence,
            'suggestion' => $suggestion
        ],
        'feedback_id' => $feedbackId,
        'email_sent' => $emailResult['success']
    ];
    
    echo json_encode($responseData);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 