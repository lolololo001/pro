<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../config/config.php';
require_once '../includes/email_helper_new.php';

session_start();
if (!isset($_SESSION['parent_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get parent information
$parentId = $_SESSION['parent_id'];
$parentName = isset($_SESSION['parent_name']) ? $_SESSION['parent_name'] : 'Parent User';
$parentEmail = isset($_SESSION['parent_email']) ? $_SESSION['parent_email'] : '';

// Initialize variables
$error = '';
$success = '';
$feedbackError = '';
$feedbackSuccess = '';
$children = [];
$requests = []; // Initialize requests array

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    error_log('Feedback handler reached');
    $feedbackText = trim($_POST['message'] ?? '');
    $feedbackType = trim($_POST['feedback_type'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    
    if (empty($feedbackText)) {
        $feedbackError = 'Please enter your feedback.';
    } elseif (empty($feedbackType)) {
        $feedbackError = 'Please select a feedback type.';
    } elseif (empty($subject)) {
        $feedbackError = 'Please enter a subject for your feedback.';
    }
    if (!$feedbackError) {
        $conn = null;
        $stmt = null;
        try {
            $conn = getDbConnection();
            // First, get the parent's email if not in session
            if (empty($parentEmail)) {
                $parentStmt = $conn->prepare("SELECT email, CONCAT(first_name, ' ', last_name) as full_name FROM parents WHERE id = ?");
                $parentStmt->bind_param('i', $parentId);
                $parentStmt->execute();
                $parentResult = $parentStmt->get_result();
                if ($parentRow = $parentResult->fetch_assoc()) {
                    $parentEmail = $parentRow['email'];
                    $parentName = $parentRow['full_name'];
                }
                $parentStmt->close();
            }
            if (empty($parentEmail)) {
                throw new Exception('Parent email not found in the system.');
            }
            $conn->begin_transaction();
            // Get the school_id
            $schoolQuery = "SELECT s.school_id 
                          FROM students s 
                          INNER JOIN student_parent sp ON s.id = sp.student_id 
                          WHERE sp.parent_id = ? 
                          LIMIT 1";
            $stmt = $conn->prepare($schoolQuery);
            if (!$stmt) {
                throw new Exception('Failed to prepare school query: ' . $conn->error);
            }
            $stmt->bind_param('i', $parentId);
            if (!$stmt->execute()) {
                throw new Exception('Failed to get school info: ' . $stmt->error);
            }
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception('No school found for this parent.');
            }
            $schoolData = $result->fetch_assoc();
            $schoolId = $schoolData['school_id'];
            $stmt->close();

            // Sentiment analysis via Python script
            $escapedFeedback = escapeshellarg($feedbackText);
            $pythonScript = escapeshellcmd(__DIR__ . '/../python/sentiment_analysis.py');
            $command = "python $pythonScript $escapedFeedback";
            $output = shell_exec($command);
            $result = json_decode($output, true);
            
            // Fallback if sentiment analysis fails
            if (!$result || !isset($result['sentiment_score'])) {
                $sentimentScore = 0.5; // neutral
                $sentimentLabel = 'neutral';
                $suggestion = 'Thank you for your feedback. We will review and address your concerns.';
            } else {
                $sentimentScore = $result['sentiment_score'];
                $sentimentLabel = $result['sentiment_label'];
                $suggestion = $result['suggestion'];
            }

            // Insert the feedback with sentiment fields
            $insertSQL = "INSERT INTO parent_feedback (parent_id, school_id, subject, sentiment_score, sentiment_label, message, suggestion, feedback_type) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertSQL);
            if (!$stmt) {
                throw new Exception('Failed to prepare feedback insert: ' . $conn->error);
            }
            $stmt->bind_param('iisdssss', $parentId, $schoolId, $subject, $sentimentScore, $sentimentLabel, $feedbackText, $suggestion, $feedbackType);
            if (!$stmt->execute()) {
                throw new Exception('Failed to submit feedback: ' . $stmt->error);
            }
            error_log('Feedback successfully inserted into parent_feedback table');
            // Send email notification using improved email helper
            $emailResult = sendFeedbackConfirmationEmail(
                $parentEmail,
                $parentName,
                $feedbackType,
                $subject,
                $feedbackText
            );
            if ($emailResult['success']) {
                $feedbackSuccess = 'Thank you! Your feedback has been submitted successfully. A confirmation email has been sent to ' . $parentEmail;
                
                // Store sentiment results for modal display
                $_SESSION['sentiment_results'] = [
                    'label' => $sentimentLabel,
                    'score' => $sentimentScore,
                    'suggestion' => $suggestion
                ];
            } else {
                $feedbackError = 'Feedback submitted, but failed to send confirmation email.';
            }
            $conn->commit();
        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollback();
            }
            $feedbackError = 'Failed to submit feedback: ' . $e->getMessage();
            error_log('Feedback submission error: ' . $e->getMessage());
        } finally {
            if (isset($stmt)) { $stmt->close(); }
            if (isset($conn)) { $conn->close(); }
        }
    }
}
  

// Children data is now fetched in the later section

// Handle permission request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permission_request'])) {
    $request_text = trim($_POST['request_text'] ?? '');
    if (empty($request_text)) {
        $error = 'Please enter your permission request.';
    } else {
        try {
            $conn = getDbConnection();
            // Check if permission_requests table exists
            $tableCheckResult = $conn->query("SHOW TABLES LIKE 'permission_requests'");
            if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
                // Table exists, get student info
                $studentStmt = $conn->prepare("SELECT s.id, s.school_id 
                                             FROM students s 
                                             JOIN student_parent sp ON s.id = sp.student_id 
                                             WHERE sp.parent_id = ? 
                                             LIMIT 1");
                if (!$studentStmt) {
                    throw new Exception("Failed to prepare student query: " . $conn->error);
                }
                $studentStmt->bind_param('i', $parentId);
                if (!$studentStmt->execute()) {
                    throw new Exception("Failed to execute student query: " . $studentStmt->error);
                }
                $studentResult = $studentStmt->get_result();
                if ($studentResult->num_rows > 0) {
                    $studentRow = $studentResult->fetch_assoc();
                    $studentId = $studentRow['id'];
                    // Insert permission request
                    $currentDate = date('Y-m-d H:i:s');
                    $tomorrowDate = date('Y-m-d H:i:s', strtotime('+1 day'));
                    $stmt = $conn->prepare("INSERT INTO permission_requests 
                                          (student_id, parent_id, request_type, start_date, end_date, reason, status) 
                                          VALUES (?, ?, 'other', ?, ?, ?, 'pending')");
                    if (!$stmt) {
                        throw new Exception("Failed to prepare insert query: " . $conn->error);
                    }
                    $stmt->bind_param('iisss', $studentId, $parentId, $currentDate, $tomorrowDate, $request_text);
                    if ($stmt->execute()) {
                        $success = 'Your permission request has been submitted successfully.';
                        // Prevent resubmission on refresh
                        $_POST = array();
                        $_SERVER['REQUEST_METHOD'] = 'GET';
                        header('Location: dashboard.php?permission_success=1');
                        exit;
                    } else {
                        throw new Exception("Failed to submit request: " . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    $error = 'No student associated with your account. Please contact the school administrator.';
                }
                
                $studentStmt->close();
            } else {
                // Create a simplified table if it doesn't exist
                $createTableSQL = "CREATE TABLE IF NOT EXISTS permission_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    parent_id INT NOT NULL,
                    request_text TEXT NOT NULL,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                if (!$conn->query($createTableSQL)) {
                    throw new Exception("Failed to create permission_requests table: " . $conn->error);
                }
                
                // Insert request with simplified structure
                $stmt = $conn->prepare("INSERT INTO permission_requests (parent_id, request_text) VALUES (?, ?)");
                
                if (!$stmt) {
                    throw new Exception("Failed to prepare insert query: " . $conn->error);
                }
                
                $stmt->bind_param('is', $parentId, $request_text);
                
                if ($stmt->execute()) {
                    $success = 'Your permission request has been submitted successfully.';
                } else {
                    throw new Exception("Failed to submit request: " . $stmt->error);
                }
                
                $stmt->close();
            }
        } catch (Exception $e) {
            $error = 'System error: ' . $e->getMessage();
            error_log("Permission request error: " . $e->getMessage());
        } finally {
            if (isset($conn)) {
                $conn->close();
            }
        }
    }
}

// Fetch children associated with this parent
try {
    $conn = getDbConnection();
    $children = [];
    
    // Check if student_parent table exists
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'student_parent'");
    if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
        // Get all children associated with this parent
        $stmt = $conn->prepare("SELECT sp.student_id, sp.is_primary FROM student_parent sp WHERE sp.parent_id = ?");
        $stmt->bind_param('i', $parentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Get student details for each associated child
            while ($row = $result->fetch_assoc()) {
                $student_id = $row['student_id'];
                $is_primary = $row['is_primary'];
                
                // Get student details
                $student_stmt = $conn->prepare("SELECT s.*, sc.name as school_name 
                                             FROM students s 
                                             JOIN schools sc ON s.school_id = sc.id 
                                             WHERE s.id = ?");
                $student_stmt->bind_param('i', $student_id);
                $student_stmt->execute();
                $student_result = $student_stmt->get_result();
                
                if ($student_result->num_rows > 0) {
                    $student = $student_result->fetch_assoc();
                    $student['is_primary'] = $is_primary;
                    $children[] = $student;
                }
                
                $student_stmt->close();
            }
        }
        
        $stmt->close();
    }
    
    // Fetch previous requests
    $requests = [];
    
    // Check if permission_requests table exists
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'permission_requests'");
    if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
        // Table exists, check its structure
        $columnsResult = $conn->query("SHOW COLUMNS FROM permission_requests");
        if (!$columnsResult) {
            throw new Exception("Failed to get permission_requests table structure: " . $conn->error);
        }
        
        $columns = [];
        while ($row = $columnsResult->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        // Check if we're using the standard schema or simplified version
        $stmt = null;
        
        if (in_array('reason', $columns) && in_array('student_id', $columns)) {
            // Using standard schema - check if students table exists
            $studentTableCheck = $conn->query("SHOW TABLES LIKE 'students'");
            if ($studentTableCheck && $studentTableCheck->num_rows > 0) {
                // Get student field names
                $studentColumnsResult = $conn->query("SHOW COLUMNS FROM students");
                $studentColumns = [];
                if ($studentColumnsResult) {
                    while ($row = $studentColumnsResult->fetch_assoc()) {
                        $studentColumns[] = $row['Field'];
                    }
                }
                
                // Determine which fields to use for student name and ID
                $nameFields = "''";
                $idField = "''";
                
                if (in_array('first_name', $studentColumns) && in_array('last_name', $studentColumns)) {
                    $nameFields = "CONCAT(s.first_name, ' ', s.last_name)";
                } else if (in_array('name', $studentColumns)) {
                    $nameFields = "s.name";
                }
                
                if (in_array('admission_number', $studentColumns)) {
                    $idField = "s.admission_number";
                } else if (in_array('registration_number', $studentColumns)) {
                    $idField = "s.registration_number";
                }
                
                // Using standard schema with student join
                $stmt = $conn->prepare("SELECT pr.id, pr.reason as request_text, pr.status, pr.created_at, pr.response_comment,
                                      $nameFields as student_name, $idField as student_id 
                                      FROM permission_requests pr 
                                      LEFT JOIN students s ON pr.student_id = s.id 
                                      WHERE pr.parent_id = ? 
                                      ORDER BY pr.created_at DESC");
            } else {
                // Students table doesn't exist, use simplified query
                $stmt = $conn->prepare("SELECT id, reason as request_text, status, created_at, response_comment
                                      FROM permission_requests 
                                      WHERE parent_id = ? 
                                      ORDER BY created_at DESC");
            }
        } else {
            // Using simplified schema
            $stmt = $conn->prepare("SELECT id, request_text, status, created_at, response_comment
                                  FROM permission_requests 
                                  WHERE parent_id = ? 
                                  ORDER BY created_at DESC");
        }
        
        if (!$stmt) {
            throw new Exception("Failed to prepare permission requests query: " . $conn->error);
        }
        
        $stmt->bind_param('i', $parentId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute permission requests query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception("Failed to get result from permission requests query: " . $stmt->error);
        }
        
        $requests = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $conn->close();
} catch (Exception $e) {
    // Log the error but show empty requests to the user
    error_log("Parent dashboard permission requests error: " . $e->getMessage());
    $requests = [];
}

// --- School and Student Search Feature ---
$search_error = '';
$search_result = null;
$schools = []; // This will be used for both student search and add child modal

try {
    $conn = getDbConnection();
    
    // Check if schools table exists
    $schoolTableCheck = $conn->query("SHOW TABLES LIKE 'schools'");
    if ($schoolTableCheck && $schoolTableCheck->num_rows > 0) {
        // Table exists, fetch schools
        $schoolRes = $conn->query('SELECT id, name FROM schools ORDER BY name');
        if ($schoolRes) {
            while ($row = $schoolRes->fetch_assoc()) {
                $schools[] = $row;
            }
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_search'])) {
        $selected_school = intval($_POST['school_id'] ?? 0);
        $student_query = trim($_POST['student_query'] ?? '');
        
        if ($selected_school && $student_query) {
            // Check if students table exists and has the expected structure
            $studentTableCheck = $conn->query("SHOW TABLES LIKE 'students'");
            
            if ($studentTableCheck && $studentTableCheck->num_rows > 0) {
                // Check for column names to determine the correct query
                $columnsResult = $conn->query("SHOW COLUMNS FROM students");
                if (!$columnsResult) {
                    throw new Exception("Failed to get student table structure: " . $conn->error);
                }
                
                $columns = [];
                while ($row = $columnsResult->fetch_assoc()) {
                    $columns[] = $row['Field'];
                }
                
                // Determine which fields to use based on available columns
                $stmt = null;
                
                if (in_array('first_name', $columns) && in_array('last_name', $columns)) {
                    // Check if admission_number exists, otherwise use registration_number
                    $id_field = in_array('admission_number', $columns) ? 'admission_number' : 'registration_number';
                    
                    // Using first_name and last_name fields
                    $stmt = $conn->prepare("SELECT s.*, sc.name as school_name 
                                          FROM students s 
                                          JOIN schools sc ON s.school_id = sc.id 
                                          WHERE s.school_id = ? 
                                          AND (CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR s.$id_field LIKE ?)");
                } else if (in_array('name', $columns)) {
                    // Check if admission_number exists, otherwise use registration_number
                    $id_field = in_array('admission_number', $columns) ? 'admission_number' : 'registration_number';
                    
                    // Using single name field
                    $stmt = $conn->prepare("SELECT s.*, sc.name as school_name 
                                          FROM students s 
                                          JOIN schools sc ON s.school_id = sc.id 
                                          WHERE s.school_id = ? 
                                          AND (s.name LIKE ? OR s.$id_field LIKE ?)");
                } else {
                    // Fallback to a more generic query
                    $stmt = $conn->prepare('SELECT s.*, sc.name as school_name 
                                          FROM students s 
                                          JOIN schools sc ON s.school_id = sc.id 
                                          WHERE s.school_id = ?');
                }
                
                if (!$stmt) {
                    throw new Exception("Failed to prepare student search query: " . $conn->error);
                }
                
                $like_query = "%" . $student_query . "%";
                
                // Bind parameters based on the number of parameters in the prepared statement
                if ($stmt->param_count == 3) {
                    $stmt->bind_param('iss', $selected_school, $like_query, $student_query);
                } else if ($stmt->param_count == 1) {
                    $stmt->bind_param('i', $selected_school);
                } else {
                    throw new Exception("Unexpected number of parameters in prepared statement");
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to execute student search query: " . $stmt->error);
                }
                
                $res = $stmt->get_result();
                if (!$res) {
                    throw new Exception("Failed to get result from student search query: " . $stmt->error);
                }
                
                $search_result = $res->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                if (empty($search_result)) {
                    $search_error = 'No student found with that name or registration number in the selected school.';
                }
            } else {
                $search_error = 'Student information is not available in the system.';
            }
        } else {
            $search_error = 'Please select a school and enter a student name or registration number.';
        }
    }
    $conn->close();
} catch (Exception $e) {
    $search_error = 'System error: ' . $e->getMessage();
    // Log the error for debugging
    error_log("Parent dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
                    /* Advanced Modern Form Design */
                    :root {
                        --primary: #00704A;
                        --primary-dark: #006241;
                        --primary-light: #D4E9D7;
                        --accent: #FFB342;
                        --text: #2C3E50;
                        --text-light: #7F8C8D;
                        --white: #FFFFFF;
                        --error: #E74C3C;
                        --success: #27AE60;
                    }

                    .feedback-section {
                        padding: 2rem;
                        perspective: 1000px;
                    }

                    .feedback-form {
                        background: linear-gradient(135deg, var(--white) 0%, #f8faf9 100%);
                        padding: 3rem;
                        border-radius: 24px;
                        box-shadow: 0 20px 60px rgba(0, 112, 74, 0.15),
                                  0 8px 20px rgba(0, 112, 74, 0.1),
                                  inset 0 2px 10px rgba(255, 255, 255, 0.5);
                        border: 1px solid rgba(0, 112, 74, 0.08);
                        position: relative;
                        overflow: hidden;
                        backdrop-filter: blur(10px);
                        transform: translateZ(0);
                        max-width: 1000px;
                        margin: 0 auto;
                        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                        animation: formAppear 0.6s ease-out forwards;
                    }

                    @keyframes formAppear {
                        0% {
                            opacity: 0;
                            transform: translateY(30px) rotateX(-10deg);
                        }
                        100% {
                            opacity: 1;
                            transform: translateY(0) rotateX(0);
                        }
                    }

                    .feedback-form:hover {
                        transform: translateY(-5px);
                        box-shadow: 0 30px 70px rgba(0, 112, 74, 0.2),
                                  0 10px 30px rgba(0, 112, 74, 0.15);
                    }

                    .feedback-header {
                        text-align: center;
                        margin-bottom: 3rem;
                        position: relative;
                        z-index: 1;
                    }

                    .feedback-icon {
                        width: 80px;
                        height: 80px;
                        margin: 0 auto 1.5rem;
                        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        position: relative;
                        animation: iconFloat 3s ease-in-out infinite;
                    }

                    @keyframes iconFloat {
                        0%, 100% { transform: translateY(0); }
                        50% { transform: translateY(-10px); }
                    }

                    .feedback-icon i {
                        font-size: 2.5rem;
                        color: var(--white);
                        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                    }

                    .feedback-icon::after {
                        content: '';
                        position: absolute;
                        width: 100%;
                        height: 100%;
                        border-radius: 50%;
                        background: inherit;
                        filter: blur(10px);
                        opacity: 0.6;
                        z-index: -1;
                    }

                    .feedback-header h3 {
                        color: var(--primary-dark);
                        font-size: 2rem;
                        font-weight: 600;
                        margin-bottom: 1rem;
                        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                    }

                    .feedback-header p {
                        color: var(--text-light);
                        font-size: 1.1rem;
                        line-height: 1.6;
                        max-width: 600px;
                        margin: 0 auto;
                    }

                    .form-grid {
                        display: grid;
                        grid-template-columns: repeat(2, 1fr);
                        gap: 2rem;
                        margin-bottom: 2rem;
                    }

                    .form-group {
                        position: relative;
                        margin-bottom: 2rem;
                        transform-style: preserve-3d;
                    }

                    .form-label {
                        position: absolute;
                        top: -10px;
                        left: 15px;
                        background: var(--white);
                        padding: 0 10px;
                        color: var(--primary);
                        font-size: 0.9rem;
                        font-weight: 600;
                        transform-origin: left;
                        transition: all 0.3s ease;
                        z-index: 1;
                    }

                    .form-label i {
                        margin-right: 6px;
                        font-size: 1rem;
                        color: var(--primary);
                        transition: all 0.3s ease;
                    }

                    .form-control {
                        width: 100%;
                        padding: 1.2rem;
                        border: 2px solid rgba(0, 112, 74, 0.1);
                        border-radius: 12px;
                        background: rgba(255, 255, 255, 0.8);
                        font-size: 1rem;
                        color: var(--text);
                        transition: all 0.3s ease;
                        backdrop-filter: blur(4px);
                    }

                    .form-control:focus {
                        border-color: var(--primary);
                        background: var(--white);
                        box-shadow: 0 0 0 4px rgba(0, 112, 74, 0.1);
                        outline: none;
                    }

                    .custom-select {
                        appearance: none;
                        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2300704A'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
                        background-repeat: no-repeat;
                        background-position: right 1rem center;
                        background-size: 1.5rem;
                        padding-right: 3rem;
                        cursor: pointer;
                    }

                    .form-text {
                        margin-top: 0.5rem;
                        font-size: 0.85rem;
                        color: var(--text-light);
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                        opacity: 0.8;
                        transition: opacity 0.3s ease;
                    }

                    .form-group:hover .form-text {
                        opacity: 1;
                    }

                    .submit-btn {
                        position: relative;
                        background: linear-gradient(45deg, var(--primary) 0%, var(--primary-dark) 100%);
                        border: none;
                        padding: 1.2rem 3rem;
                        font-size: 1.1rem;
                        font-weight: 600;
                        color: var(--white);
                        border-radius: 12px;
                        cursor: pointer;
                        overflow: hidden;
                        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                        box-shadow: 0 4px 15px rgba(0, 112, 74, 0.2);
                        width: auto;
                        margin: 2rem auto 0;
                        display: block;
                    }

                    .submit-btn:hover {
                        transform: translateY(-2px) scale(1.02);
                        box-shadow: 0 8px 25px rgba(0, 112, 74, 0.3);
                    }

                    .submit-btn:active {
                        transform: translateY(1px) scale(0.98);
                    }

                    .submit-btn i {
                        margin-right: 10px;
                        font-size: 1.2rem;
                        vertical-align: middle;
                        transform: translateY(-1px);
                        transition: transform 0.3s ease;
                    }

                    .submit-btn:hover i {
                        transform: translateY(-1px) translateX(3px);
                    }

                    .btn-hover-effect {
                        position: absolute;
                        top: -50%;
                        left: -25%;
                        width: 150%;
                        height: 200%;
                        background: linear-gradient(
                            90deg,
                            transparent,
                            rgba(255, 255, 255, 0.2),
                            transparent
                        );
                        transform: skewX(-25deg);
                        animation: shine 6s infinite;
                    }

                    @keyframes shine {
                        0% { left: -25%; opacity: 0; }
                        25% { opacity: 1; }
                        50% { left: 125%; opacity: 0; }
                        100% { left: 125%; opacity: 0; }
                    }

                    /* Advanced Textarea Styling */
                    textarea.form-control {
                        min-height: 180px;
                        line-height: 1.6;
                        resize: vertical;
                        background-image: linear-gradient(transparent, transparent calc(1.6em - 1px), #e0e0e0 0);
                        background-size: 100% 1.6em;
                        background-position-y: 1px;
                    }

                    /* Custom Scrollbar */
                    .form-control::-webkit-scrollbar {
                        width: 8px;
                    }

                    .form-control::-webkit-scrollbar-track {
                        background: rgba(0, 112, 74, 0.05);
                        border-radius: 4px;
                    }

                    .form-control::-webkit-scrollbar-thumb {
                        background: var(--primary);
                        border-radius: 4px;
                        transition: all 0.3s ease;
                    }

                    .form-control::-webkit-scrollbar-thumb:hover {
                        background: var(--primary-dark);
                    }

                    /* Success Message Animation */
                    .alert {
                        border-radius: 12px;
                        padding: 1.2rem;
                        margin-bottom: 2rem;
                        position: relative;
                        overflow: hidden;
                        animation: slideIn 0.5s ease-out forwards;
                    }

                    .alert-success {
                        background: rgba(39, 174, 96, 0.1);
                        border-left: 4px solid var(--success);
                        color: var(--success);
                    }

                    .alert-error {
                        background: rgba(231, 76, 60, 0.1);
                        border-left: 4px solid var(--error);
                        color: var(--error);
                    }

                    @keyframes slideIn {
                        0% {
                            opacity: 0;
                            transform: translateY(-20px);
                        }
                        100% {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }

                    /* Responsive Design */
                    @media (max-width: 768px) {
                        .feedback-form {
                            padding: 2rem;
                            margin: 1rem;
                        }

                        .form-grid {
                            grid-template-columns: 1fr;
                            gap: 1rem;
                        }

                        .feedback-icon {
                            width: 60px;
                            height: 60px;
                        }

                        .feedback-header h3 {
                            font-size: 1.5rem;
                        }

                        .submit-btn {
                            width: 100%;
                            padding: 1rem 2rem;
                        }
                    }

                    /* Loading State */
                    .submit-btn.loading {
                        position: relative;
                        pointer-events: none;
                    }

                    .submit-btn.loading::after {
                        content: '';
                        position: absolute;
                        width: 20px;
                        height: 20px;
                        border: 2px solid transparent;
                        border-top-color: var(--white);
                        border-radius: 50%;
                        animation: spin 0.8s linear infinite;
                        right: 1.5rem;
                        top: calc(50% - 10px);
                    }

                    @keyframes spin {
                        to { transform: rotate(360deg); }
                    }

                    /* Field Focus Effect */
                    .form-group.focused .form-label {
                        transform: translateY(-5px) scale(0.95);
                        color: var(--primary-dark);
                    }

                    .form-group.focused .form-label i {
                        transform: scale(1.1);
                    }

                    /* Tooltip for status badge */
                    .popover-status {
                        position: relative;
                        cursor: pointer;
                    }
                    .popover-status::after {
                        content: attr(data-tooltip);
                        display: none;
                        position: absolute;
                        left: 50%;
                        top: 120%;
                        transform: translateX(-50%);
                        min-width: 180px;
                        max-width: 350px;
                        background: #fff;
                        color: #333;
                        border: 1px solid #ccc;
                        border-radius: 8px;
                        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
                        padding: 12px 16px;
                        font-size: 0.95em;
                        z-index: 100;
                        white-space: pre-line;
                        opacity: 0;
                        pointer-events: none;
                        transition: opacity 0.2s;
                    }
                    .popover-status:hover::after {
                        display: block;
                        opacity: 1;
                        pointer-events: none;
                    }
                </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo"><?php echo APP_NAME; ?><span>.</span></a>
        </div>
        
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($parentName); ?></h3>
                <p>Parent</p>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-heading">Navigation</div>
            
            <div class="menu-item active">
                <i class="fas fa-tachometer-alt"></i>
                <a href="dashboard.php">Dashboard</a>
            </div>
            
            <div class="menu-item">
                <i class="fas fa-user-graduate"></i>
                <a href="#students">My Children</a>
            </div>
            
            <div class="menu-item">
                <i class="fas fa-clipboard-list"></i>
                <a href="#permissions">Permission Requests</a>
            </div>
            
            <div class="menu-item">
                <i class="fas fa-money-bill-wave"></i>
                <a href="#fees">Fee Information</a>
            </div>
            
            <div class="menu-item">
                <i class="fas fa-chart-line"></i>
                <a href="#academics">Academic Progress</a>
            </div>
            
            <div class="menu-heading">Account</div>
            
            <div class="menu-item">
                <i class="fas fa-user-cog"></i>
                <a href="profile.php">My Profile</a>
            </div>
            
            <div class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <a href="#" class="logout-link">Logout</a>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1>Parent Dashboard</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <a href="dashboard.php">Dashboard</a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['add_child_error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['add_child_error']; unset($_SESSION['add_child_error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['add_child_success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['add_child_success']; unset($_SESSION['add_child_success']); ?></div>
        <?php endif; ?>
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($search_result ?? []); ?></h3>
                    <p>My Children</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($requests ?? []); ?></h3>
                    <p>Permission Requests</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo date('M Y'); ?></h3>
                    <p>Current Term</p>
                </div>
            </div>
        </div>
        
        <!-- Student Information Cards -->
        <div class="card" id="students">
            <div class="card-header">
                <h2><i class="fas fa-user-graduate"></i> My Children</h2>
                <a href="#" data-toggle="modal" data-target="#addChildModal">Add Child</a>
            </div>
            <div class="card-body">
                <?php if ($children && count($children) > 0): ?>
                    <div class="student-cards">
                        <?php foreach ($children as $student): ?>
                            <div class="student-card">
                                <div class="student-card-header">
                                    <h3><?php echo htmlspecialchars(isset($student['name']) ? $student['name'] : (isset($student['first_name']) ? $student['first_name'] . ' ' . $student['last_name'] : 'N/A')); ?></h3>
                                    <?php if ($student['is_primary']): ?>
                                        <span class="badge primary-badge">Primary</span>
                                    <?php endif; ?>
                                </div>
                                <div class="student-card-body">
                                    <div class="student-card-avatar">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div class="student-info">
                                        <p><strong><?php echo htmlspecialchars(isset($student['registration_number']) ? $student['registration_number'] : (isset($student['admission_number']) ? $student['admission_number'] : 'N/A')); ?></strong></p>
                                        <p><?php echo htmlspecialchars($student['school_name'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="student-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Class:</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($student['class'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Admission Date:</span>
                                            <span class="detail-value"><?php echo isset($student['admission_date']) ? date('M d, Y', strtotime($student['admission_date'])) : 'N/A'; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Status:</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($student['status'] ?? 'Active'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>You don't have any children associated with your account yet. Use the "Add Child" button to add your children.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Permission Requests Section -->
        <div class="card" id="permissions">
            <div class="card-header">
                <h2><i class="fas fa-clipboard-list"></i> Permission Requests</h2>
                <a href="#" data-toggle="modal" data-target="#newRequestModal">New Request</a>
            </div>
            <div class="card-body">
                <!-- New Permission Request Form -->
                <form method="POST" class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
                    <h3><i class="fas fa-plus-circle"></i> New Permission Request</h3>
                    <div class="form-group">
                        <label for="request_type">Request Type</label>
                        <select name="request_type" id="request_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="leave">Leave of Absence</option>
                            <option value="medical">Medical Appointment</option>
                            <option value="event">School Event</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="student_select">Select Child</label>
                        <select name="student_select" id="student_select">
                            <?php if (!empty($children)): ?>
                                <?php foreach ($children as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars(isset($student['name']) ? $student['name'] : (isset($student['first_name']) ? $student['first_name'] . ' ' . $student['last_name'] : 'N/A')); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">No children associated</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="datetime-local" name="start_date" id="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="datetime-local" name="end_date" id="end_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="request_text">Permission Request Details</label>
                        <textarea name="request_text" id="request_text" required></textarea>
                    </div>
                    
                    <button type="submit" name="permission_request" class="btn">Submit Request</button>
                </form>
                
                <!-- Previous Requests Table -->
                <h3><i class="fas fa-history"></i> Your Previous Requests</h3>
                <?php if (empty($requests)): ?>
                    <div class="alert alert-warning">
                        <p>You haven't made any permission requests yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Request</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td>
                                            <?php if (isset($request['student_name']) && !empty($request['student_name'])): ?>
                                                <?php echo htmlspecialchars($request['student_name']); ?>
                                                <br><small><?php echo htmlspecialchars($request['student_id']); ?></small>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['request_text']); ?></td>
                                        <td>
                                            <?php if (isset($request['start_date']) && isset($request['end_date'])): ?>
                                                <?php echo date('M d, Y', strtotime($request['start_date'])); ?> to
                                                <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $status = strtolower($request['status']);
                                                $badgeClass = 'status-badge status-' . $status . ' popover-status';
                                                $comment = isset($request['response_comment']) ? $request['response_comment'] : '';
                                                $tooltip = '';
                                                if ($status === 'pending') {
                                                    $tooltip = 'Wait for the admin to react';
                                                } elseif ($status === 'approved' || $status === 'rejected') {
                                                    // Only show tooltip if comment is not null and not empty after trim
                                                    if (!is_null($comment) && trim($comment) !== '') {
                                                        $tooltip = trim($comment);
                                                    } else {
                                                        $tooltip = '';
                                                    }
                                                } else {
                                                    $tooltip = ucfirst($status);
                                                }
                                            ?>
                                            <?php if ($tooltip !== ''): ?>
                                                <span class="<?php echo $badgeClass; ?>" data-tooltip="<?php echo htmlspecialchars($tooltip, ENT_QUOTES); ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="<?php echo $badgeClass; ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Fee Information Section -->
        <div class="card" id="fees">
            <div class="card-header">
                <h2><i class="fas fa-money-bill-wave"></i> Fee Information</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p>Fee information will be available soon. Please check back later.</p>
                </div>
            </div>
        </div>
          <!-- Academic Progress Section -->
        <div class="card" id="academics">
            <div class="card-header">
                <h2><i class="fas fa-chart-line"></i> Academic Progress</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p>Academic progress information will be available soon. Please check back later.</p>
                </div>
            </div>
        </div>

        <!-- Feedback Section -->
        <div class="card" id="feedback">
            <div class="card-header">
                <h2><i class="fas fa-comment"></i> Provide Feedback</h2>
            </div>
            <divb
                <?php if ($feedbackSuccess): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($feedbackSuccess); ?>
                    </div>
                    
                    <?php if (isset($_SESSION['sentiment_results'])): ?>
                        <script>
                            // Show sentiment analysis results
                            document.addEventListener('DOMContentLoaded', function() {
                                const results = <?php echo json_encode($_SESSION['sentiment_results']); ?>;
                                showSentimentResults(results.label, results.score, results.suggestion);
                            });
                        </script>
                        <?php unset($_SESSION['sentiment_results']); ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($feedbackError): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($feedbackError); ?>
                    </div>
                <?php endif; ?>                <!-- Feedback Form -->
                <div class="feedback-section">
                    <form method="POST" class="feedback-form" id="feedbackForm">
                        <div class="feedback-header">
                            <div class="feedback-icon">
                                <i class="fas fa-comments"></i>
                            </div>
                            <h3>Share Your Valuable Feedback</h3>
                            <p>Your insights help us create a better educational experience. We value your thoughts and suggestions!</p>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <input type="text" class="form-control" id="subject" name="subject" 
                                       placeholder=" " required>
                                <label for="subject" class="form-label">
                                    <i class="fas fa-heading"></i>
                                    Subject
                                </label>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i>
                                    Brief title for your feedback
                                </div>
                            </div>

                            <div class="form-group">
                                <select class="form-control custom-select" id="feedback_type" 
                                        name="feedback_type" required>
                                    <option value="" disabled selected>Select category</option>
                                    <option value="Academic">📚 Academic Experience</option>
                                    <option value="Administrative">🏢 Administrative Services</option>
                                    <option value="Facility">🏫 School Facilities</option>
                                    <option value="Teacher">👩‍🏫 Teaching Staff</option>
                                    <option value="Safety">🛡️ Safety & Security</option>
                                    <option value="Communication">💬 School Communication</option>
                                    <option value="Suggestion">💡 Suggestions</option>
                                    <option value="Other">✨ Other Feedback</option>
                                </select>
                                <label for="feedback_type" class="form-label">
                                    <i class="fas fa-tag"></i>
                                    Feedback Category
                                </label>
                                <div class="form-text">
                                    <i class="fas fa-filter"></i>
                                    Choose the most relevant category
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <textarea class="form-control" id="message" name="message" 
                                    placeholder=" " required></textarea>
                            <label for="message" class="form-label">
                                <i class="fas fa-pen-fancy"></i>
                                Your Message
                            </label>
                            <div class="form-text">
                                <i class="fas fa-heart"></i>
                                Share your thoughts, experiences, or suggestions in detail
                            </div>
                        </div>

                        <button type="submit" name="submit_feedback" class="submit-btn" id="submitButton">
                            <i class="fas fa-paper-plane"></i>
                            Submit Feedback
                            <span class="btn-hover-effect"></span>
                        </button>
                    </form>
                </div>

                <!-- Sentiment Analysis Result Modal -->
                <div id="sentimentModal" class="modal" style="display:none;">
                    <div class="modal-content" style="max-width: 500px; border-radius: 16px; padding: 2rem; text-align: center;">
                        <span class="close" onclick="closeSentimentModal()" style="float:right; font-size:1.5rem; cursor:pointer;">&times;</span>
                        <div id="sentimentResultIcon" style="font-size:2.5rem; margin-bottom:1rem;"></div>
                        <h3 id="sentimentResultLabel"></h3>
                        <p id="sentimentResultScore" style="font-weight:500;"></p>
                        <div id="sentimentSuggestion" style="margin-top:1.5rem; color:#00704A; font-weight:500;"></div>
                        <button class="btn btn-primary" onclick="closeSentimentModal()" style="margin-top:2rem;">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include the Add Child Modal -->
    <?php include 'add_child_modal.php'; ?>
    
    <script>
        // JavaScript for smooth scrolling to sections
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 20,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Get all elements with data-toggle="modal"
            const modalTriggers = document.querySelectorAll('[data-toggle="modal"]');
            
            modalTriggers.forEach(trigger => {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    const modalId = this.getAttribute('data-target');
                    const modal = document.querySelector(modalId);
                    
                    if (modal) {
                        modal.style.display = 'block';
                    }
                });
            });
            
            // Close modal when clicking on close button or outside the modal
            const closeButtons = document.querySelectorAll('[data-dismiss="modal"]');
            
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                });
            });
            
            // Close modal when clicking outside of it
            window.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal')) {
                    e.target.style.display = 'none';
                }
            });
        });
    </script>

    <!-- Logout Modal -->
    <div id="logoutConfirmModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2><i class="fas fa-sign-out-alt"></i> Confirm Logout</h2>
                <span class="close-modal" onclick="closeModal('logoutConfirmModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to logout?</p>
                <div class="form-actions" style="margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('logoutConfirmModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="window.location.href='../logout.php'">
                        <i class="fas fa-check"></i> Yes, Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {            const logoutLinks = document.querySelectorAll('.logout-link, a[href="../logout.php"]');
            logoutLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('logoutConfirmModal').style.display = 'block';
                });
            });

            // Function to close modals
            window.closeModal = function(modalId) {
                document.getElementById(modalId).style.display = 'none';
            };
        });
    </script>
    <script src="../includes/js/feedback-ajax.js"></script>
    <script>
        // Function to close sentiment modal
        function closeSentimentModal() {
            document.getElementById('sentimentModal').style.display = 'none';
        }
        
        // Function to show sentiment analysis results
        function showSentimentResults(sentimentLabel, sentimentScore, suggestion) {
            const modal = document.getElementById('sentimentModal');
            const icon = document.getElementById('sentimentResultIcon');
            const label = document.getElementById('sentimentResultLabel');
            const score = document.getElementById('sentimentResultScore');
            const suggestionDiv = document.getElementById('sentimentSuggestion');
            
            // Set icon based on sentiment
            if (sentimentLabel === 'positive') {
                icon.innerHTML = '😊';
                icon.style.color = '#4CAF50';
            } else if (sentimentLabel === 'negative') {
                icon.innerHTML = '😔';
                icon.style.color = '#F44336';
            } else {
                icon.innerHTML = '😐';
                icon.style.color = '#FF9800';
            }
            
            // Set content
            label.textContent = `Sentiment: ${sentimentLabel.charAt(0).toUpperCase() + sentimentLabel.slice(1)}`;
            score.textContent = `Confidence: ${Math.round(sentimentScore * 100)}%`;
            suggestionDiv.innerHTML = `<strong>Suggestion:</strong><br>${suggestion}`;
            
            // Show modal
            modal.style.display = 'block';
        }
        
        // Check if there are sentiment results in session storage
        document.addEventListener('DOMContentLoaded', function() {
            const sentimentData = sessionStorage.getItem('sentimentResults');
            if (sentimentData) {
                const data = JSON.parse(sentimentData);
                showSentimentResults(data.label, data.score, data.suggestion);
                sessionStorage.removeItem('sentimentResults');
            }
        });
    </script>
</body>
</html>