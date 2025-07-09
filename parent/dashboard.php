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
    $request_type = trim($_POST['request_type'] ?? 'other');
    $student_select = intval($_POST['student_select'] ?? 0);
    $start_date = $_POST['start_date'] ?? date('Y-m-d H:i:s');
    $end_date = $_POST['end_date'] ?? date('Y-m-d H:i:s', strtotime('+1 day'));
    
    if (empty($request_text)) {
        $error = 'Please enter your permission request.';
    } elseif ($student_select == 0) {
        $error = 'Please select a child.';
    } else {
        try {
            $conn = getDbConnection();
            // Check if permission_requests table exists
            $tableCheckResult = $conn->query("SHOW TABLES LIKE 'permission_requests'");
            if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
                // Table exists, verify student belongs to this parent
                $studentStmt = $conn->prepare("SELECT s.id, s.school_id 
                                             FROM students s 
                                             JOIN student_parent sp ON s.id = sp.student_id 
                                             WHERE sp.parent_id = ? AND s.id = ?");
                if (!$studentStmt) {
                    throw new Exception("Failed to prepare student query: " . $conn->error);
                }
                $studentStmt->bind_param('ii', $parentId, $student_select);
                if (!$studentStmt->execute()) {
                    throw new Exception("Failed to execute student query: " . $studentStmt->error);
                }
                $studentResult = $studentStmt->get_result();
                if ($studentResult->num_rows > 0) {
                    $studentRow = $studentResult->fetch_assoc();
                    $studentId = $studentRow['id'];
                    
                    // Insert permission request with proper fields
                    $stmt = $conn->prepare("INSERT INTO permission_requests 
                                          (student_id, parent_id, request_type, start_date, end_date, reason, status) 
                                          VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    if (!$stmt) {
                        throw new Exception("Failed to prepare insert query: " . $conn->error);
                    }
                    $stmt->bind_param('iissss', $studentId, $parentId, $request_type, $start_date, $end_date, $request_text);
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
                    $error = 'Selected student is not associated with your account.';
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
                $student_stmt = $conn->prepare("SELECT s.*, s.reg_number, sc.name as school_name, d.department_name 
                                             FROM students s 
                                             JOIN schools sc ON s.school_id = sc.id 
                                             LEFT JOIN departments d ON s.department_id = d.dep_id 
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
                                      pr.start_date, pr.end_date, pr.request_type, pr.updated_at,
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

                    /* Enhanced Tooltip for status badge */
                    .popover-status {
                        position: relative;
                        cursor: help;
                        transition: all 0.3s ease;
                    }
                    
                    .popover-status:hover {
                        transform: translateY(-1px);
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    }
                    
                    .popover-status::after {
                        content: attr(data-tooltip);
                        display: none;
                        position: absolute;
                        left: 50%;
                        top: 130%;
                        transform: translateX(-50%);
                        min-width: 280px;
                        max-width: 400px;
                        background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
                        color: #ecf0f1;
                        border: 1px solid #34495e;
                        border-radius: 12px;
                        box-shadow: 0 8px 32px rgba(0,0,0,0.3);
                        padding: 16px 20px;
                        font-size: 0.9em;
                        line-height: 1.5;
                        z-index: 1000;
                        white-space: pre-line;
                        opacity: 0;
                        pointer-events: none;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        font-family: 'Poppins', sans-serif;
                        text-align: left;
                        backdrop-filter: blur(10px);
                    }
                    
                    .popover-status::before {
                        content: '';
                        position: absolute;
                        left: 50%;
                        top: 120%;
                        transform: translateX(-50%);
                        border: 8px solid transparent;
                        border-bottom-color: #2c3e50;
                        display: none;
                        z-index: 1001;
                        opacity: 0;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    }
                    
                    .popover-status:hover::after,
                    .popover-status:hover::before {
                        display: block;
                        opacity: 1;
                        pointer-events: none;
                    }
                    
                    /* Status-specific tooltip styling */
                    .status-badge.status-pending.popover-status::after {
                        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
                        border-color: #e67e22;
                    }
                    
                    .status-badge.status-pending.popover-status::before {
                        border-bottom-color: #f39c12;
                    }
                    
                    .status-badge.status-approved.popover-status::after {
                        background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
                        border-color: #2ecc71;
                    }
                    
                    .status-badge.status-approved.popover-status::before {
                        border-bottom-color: #27ae60;
                    }
                    
                    .status-badge.status-rejected.popover-status::after {
                        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
                        border-color: #c0392b;
                    }
                    
                    .status-badge.status-rejected.popover-status::before {
                        border-bottom-color: #e74c3c;
                    }
                    
                    /* Status Badge Styles */
                    .status-badge {
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                        padding: 0.5rem 1rem;
                        border-radius: 25px;
                        font-size: 0.9rem;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                        transition: all 0.3s ease;
                    }
                    
                    .status-badge:hover {
                        transform: translateY(-1px);
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    }
                    
                    .status-badge.status-pending {
                        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                        color: #856404;
                        border: 1px solid #ffeaa7;
                    }
                    
                    .status-badge.status-approved {
                        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                        color: #155724;
                        border: 1px solid #c3e6cb;
                    }
                    
                    .status-badge.status-rejected {
                        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
                        color: #721c24;
                        border: 1px solid #f5c6cb;
                    }
                    
                    /* Status update animations */
                    @keyframes statusUpdate {
                        0% { transform: scale(1); }
                        50% { transform: scale(1.1); }
                        100% { transform: scale(1); }
                    }
                    
                    @keyframes slideInRight {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    
                    @keyframes slideOutRight {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                    }
                    
                    /* Form validation styles */
                    .form-control.error,
                    select.error,
                    input.error,
                    textarea.error {
                        border-color: #dc3545 !important;
                        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
                    }
                    
                    .form-control.error:focus,
                    select.error:focus,
                    input.error:focus,
                    textarea.error:focus {
                        border-color: #dc3545 !important;
                        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
                    }

                    .announcements-btn {
                        background: linear-gradient(135deg, var(--primary-color), #2563eb);
                        color: #fff;
                        padding: 0.7rem 1.3rem;
                        border-radius: 8px;
                        font-weight: 600;
                        border: none;
                        box-shadow: 0 2px 8px rgba(67,233,123,0.10);
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                        cursor: pointer;
                        transition: background 0.2s;
                    }
                    .announcements-btn:hover {
                        background: linear-gradient(135deg, #2563eb, var(--primary-color));
                    }
                    .modal-announcements-bg {
                        position: fixed;
                        top: 0; left: 0; right: 0; bottom: 0;
                        background: rgba(0,0,0,0.25);
                        z-index: 1000;
                        display: none;
                        align-items: center;
                        justify-content: center;
                    }
                    .modal-announcements {
                        background: #fff;
                        border-radius: 16px;
                        box-shadow: 0 8px 32px rgba(67,233,123,0.18);
                        max-width: 540px;
                        width: 95vw;
                        max-height: 80vh;
                        overflow-y: auto;
                        padding: 2rem 1.5rem 1.5rem 1.5rem;
                        position: relative;
                    }
                    .modal-announcements .close-modal {
                        position: absolute;
                        top: 1.1rem;
                        right: 1.3rem;
                        font-size: 1.5rem;
                        color: #888;
                        cursor: pointer;
                    }
                    .announcement-item {
                        border-bottom: 1px solid #e0e0e0;
                        padding: 1rem 0;
                    }
                    .announcement-item:last-child {
                        border-bottom: none;
                    }
                    .announcement-title {
                        font-size: 1.15rem;
                        font-weight: 700;
                        color: var(--primary-color);
                    }
                    .announcement-meta {
                        font-size: 0.95rem;
                        color: #666;
                        margin-bottom: 0.3rem;
                    }
                    .announcement-content {
                        margin: 0.5rem 0 0.2rem 0;
                        color: #222;
                    }
                    .announcement-priority.high {
                        color: #e53e3e;
                        font-weight: 600;
                    }
                    .announcement-priority.medium {
                        color: #f59e42;
                        font-weight: 600;
                    }
                    .announcement-priority.low {
                        color: #38a169;
                        font-weight: 600;
                    }
                </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo"><?php echo APP_NAME; ?></a>
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
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>Parent Dashboard</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Home</a>
                    <span>/</span>
                    <a href="dashboard.php">Dashboard</a>
                </div>
            </div>
            <button class="announcements-btn" onclick="openAnnouncementsModal()">
                <i class="fas fa-bullhorn"></i> View Announcements
            </button>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['permission_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Your permission request has been submitted successfully and is now pending review.
            </div>
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
                    <h3><?php echo count($children ?? []); ?></h3>
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
                                    <h3 style="margin-bottom: 1.2rem;"><?php echo htmlspecialchars(isset($student['name']) ? $student['name'] : (isset($student['first_name']) ? $student['first_name'] . ' ' . $student['last_name'] : '')); ?></h3>
                                    <?php if ($student['is_primary']): ?>
                                        <span class="badge primary-badge">Primary</span>
                                    <?php endif; ?>
                                </div>
                                <div class="student-card-body">
                                    <div class="student-card-avatar" style="font-size: 3.5rem; margin-bottom: 1.5rem; margin-top: 0.5rem;">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div class="student-info">
                                        <p><strong><?php 
                                            echo htmlspecialchars(
                                                isset($student['reg_number']) ? $student['reg_number'] : 
                                                (isset($student['admission_number']) ? $student['admission_number'] : '')
                                            ); 
                                        ?></strong></p>
                                        <p><?php echo htmlspecialchars($student['school_name'] ?? ''); ?></p>
                                    </div>
                                    <div class="student-details">
                                        <div class="detail-item">
                                            <span class="detail-label">Reg Number:</span>
                                            <span class="detail-value"><?php 
                                                echo htmlspecialchars(
                                                    isset($student['reg_number']) ? $student['reg_number'] : 
                                                    (isset($student['admission_number']) ? $student['admission_number'] : '')
                                                ); 
                                            ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Department:</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($student['department_name'] ?? ''); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Admission Date:</span>
                                            <span class="detail-value"><?php echo isset($student['admission_date']) && $student['admission_date'] ? date('M d, Y', strtotime($student['admission_date'])) : ''; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Status:</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($student['status'] ?? 'Active'); ?></span>
                                        </div>
                                    </div>
                                    <div style="margin-top: 1.2rem; text-align: right;">
                                        <a href="student_info.php?student_id=<?php echo urlencode($student['id'] ?? ''); ?>" class="btn btn-primary">View Info</a>
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
            </div>
            <div class="card-body">
                <!-- Two-Column Layout: Permission Form and Recent Requests -->
                <div class="permission-layout">
                    <!-- Left Column: Permission Request Form -->
                    <div class="permission-form-column">
                        <div class="permission-form-wrapper">
                            <div class="form-header-section">
                                <div class="header-icon-container">
                                    <div class="header-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="icon-glow"></div>
                                </div>
                                <div class="header-content">
                                    <h3>Permission Request</h3>
                                    <p>Submit a formal request for your child's absence or special permission</p>
                                </div>
                            </div>
                            
                            <form method="POST" class="modern-permission-form" id="permissionForm">
                                <div class="form-sections">
                                    <!-- Section 1: Basic Information -->
                                    <div class="form-section">
                                        <div class="section-header">
                                            <div class="section-number">1</div>
                                            <h4>Basic Information</h4>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-field">
                                                <label for="request_type" class="field-label">
                                                    <i class="fas fa-tag"></i>
                                                    Request Type
                                                </label>
                                                <div class="select-wrapper">
                                                    <select name="request_type" id="request_type" class="modern-select" required>
                                                        <option value="" disabled selected>Choose request type</option>
                                                        <option value="leave">🏠 Leave of Absence</option>
                                                        <option value="medical">🏥 Medical Appointment</option>
                                                        <option value="event">🎉 School Event</option>
                                                        <option value="other">📝 Other</option>
                                                    </select>
                                                    <div class="select-arrow">
                                                        <i class="fas fa-chevron-down"></i>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-field">
                                                <label for="student_select" class="field-label">
                                                    <i class="fas fa-user-graduate"></i>
                                                    Select Child
                                                </label>
                                                <div class="select-wrapper">
                                                    <select name="student_select" id="student_select" class="modern-select" required>
                                                        <option value="" disabled selected>Choose your child</option>
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
                                                    <div class="select-arrow">
                                                        <i class="fas fa-chevron-down"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Section 2: Time Details -->
                                    <div class="form-section">
                                        <div class="section-header">
                                            <div class="section-number">2</div>
                                            <h4>Time Details</h4>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-field">
                                                <label for="start_date" class="field-label">
                                                    <i class="fas fa-calendar-plus"></i>
                                                    Start Date & Time
                                                </label>
                                                <div class="input-wrapper">
                                                    <input type="datetime-local" name="start_date" id="start_date" class="modern-input" required>
                                                    <div class="input-icon">
                                                        <i class="fas fa-clock"></i>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="form-field">
                                                <label for="end_date" class="field-label">
                                                    <i class="fas fa-calendar-minus"></i>
                                                    End Date & Time
                                                </label>
                                                <div class="input-wrapper">
                                                    <input type="datetime-local" name="end_date" id="end_date" class="modern-input" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Section 3: Request Details -->
                                    <div class="form-section">
                                        <div class="section-header">
                                            <div class="section-number">3</div>
                                            <h4>Request Details</h4>
                                        </div>
                                        
                                        <div class="form-field full-width">
                                            <label for="request_text" class="field-label">
                                                <i class="fas fa-align-left"></i>
                                                Detailed Description
                                            </label>
                                            <div class="textarea-wrapper">
                                                <textarea name="request_text" id="request_text" class="modern-textarea" rows="5" required placeholder="Please provide a detailed explanation of your request........"></textarea>
                                            </div>
                                            <div class="field-hint">
                                                <i class="fas fa-lightbulb"></i>
                                                Be specific and include any supporting details that may help with approval
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submit Section -->
                                <div class="submit-section">
                                    <button type="submit" name="permission_request" class="submit-button">
                                        <div class="button-content">
                                            <i class="fas fa-paper-plane"></i>
                                            <span>Submit Permission Request</span>
                                        </div>
                                        <div class="button-glow"></div>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Right Column: Recent Requests Card -->
                    <div class="recent-requests-column">
                        <div class="recent-requests-card">
                            <div class="recent-header">
                                <div class="recent-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div class="recent-title">
                                    <h3>Recent Requests</h3>
                                    <p>Your latest permission requests</p>
                                </div>
                            </div>
                            
                            <div class="recent-content">
                                <?php if (!empty($requests)): ?>
                                    <div class="recent-requests-list">
                                        <?php 
                                        $recentCount = 0;
                                        foreach ($requests as $request): 
                                            if ($recentCount >= 5) break; // Show only 5 most recent
                                        ?>
                                            <div class="recent-request-item">
                                                <div class="request-header">
                                                    <div class="request-type-badge">
                                                        <?php 
                                                        $type = $request['request_type'] ?? 'other';
                                                        switch($type) {
                                                            case 'leave': echo '🏠'; break;
                                                            case 'medical': echo '🏥'; break;
                                                            case 'event': echo '🎉'; break;
                                                            default: echo '📝'; break;
                                                        }
                                                        ?>
                                                    </div>
                                                    <div class="request-status">
                                                        <?php 
                                                        $status = $request['status'] ?? 'pending';
                                                        $statusClass = 'status-' . $status;
                                                        $statusIcon = '';
                                                        switch($status) {
                                                            case 'pending': $statusIcon = '⏳'; break;
                                                            case 'approved': $statusIcon = '✅'; break;
                                                            case 'rejected': $statusIcon = '❌'; break;
                                                        }
                                                        ?>
                                                        <span class="status-badge <?php echo $statusClass; ?>">
                                                            <?php echo $statusIcon; ?> <?php echo ucfirst($status); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="request-content">
                                                    <div class="request-text">
                                                        <?php 
                                                        $text = $request['request_text'] ?? $request['reason'] ?? 'No description';
                                                        echo htmlspecialchars(substr($text, 0, 80)) . (strlen($text) > 80 ? '...' : '');
                                                        ?>
                                                    </div>
                                                    <div class="request-date">
                                                        <i class="fas fa-calendar-alt"></i>
                                                        <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                                    </div>
                                                </div>
                                                
                                                <?php if (isset($request['response_comment']) && !empty($request['response_comment'])): ?>
                                                    <div class="request-response">
                                                        <i class="fas fa-comment"></i>
                                                        <span><?php echo htmlspecialchars(substr($request['response_comment'], 0, 60)) . (strlen($request['response_comment']) > 60 ? '...' : ''); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php 
                                        $recentCount++;
                                        endforeach; 
                                        ?>
                                    </div>
                                    
                                    <?php if (count($requests) > 5): ?>
                                        <div class="view-all-section">
                                            <a href="#" class="view-all-link">
                                                <i class="fas fa-eye"></i>
                                                View All Requests
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="no-requests">
                                        <div class="no-requests-icon">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <h4>No Requests Yet</h4>
                                        <p>You haven't submitted any permission requests yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <style>
                /* Modern Permission Request Form Styles */
                .permission-request-container {
                    max-width: 1000px;
                    margin: 0 auto;
                }
                
                .permission-form-wrapper {
                    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                    border-radius: 24px;
                    box-shadow: 
                        0 20px 60px rgba(0, 112, 74, 0.08),
                        0 8px 20px rgba(0, 112, 74, 0.06),
                        inset 0 1px 0 rgba(255, 255, 255, 0.8);
                    border: 1px solid rgba(0, 112, 74, 0.08);
                    overflow: hidden;
                    position: relative;
                }
                
                .permission-form-wrapper::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(90deg, #00704A, #27ae60, #2ecc71);
                    z-index: 1;
                }
                
                .form-header-section {
                    background: linear-gradient(135deg, #f0f9f6 0%, #e8f5f0 100%);
                    padding: 2.5rem 2rem 2rem;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .form-header-section::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: radial-gradient(circle, rgba(39, 174, 96, 0.03) 0%, transparent 70%);
                    animation: float 6s ease-in-out infinite;
                }
                
                @keyframes float {
                    0%, 100% { transform: translate(0, 0) rotate(0deg); }
                    33% { transform: translate(30px, -30px) rotate(120deg); }
                    66% { transform: translate(-20px, 20px) rotate(240deg); }
                }
                
                .header-icon-container {
                    position: relative;
                    display: inline-block;
                    margin-bottom: 1.5rem;
                }
                
                .header-icon {
                    width: 80px;
                    height: 80px;
                    background: linear-gradient(135deg, #00704A 0%, #27ae60 50%, #2ecc71 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 2.5rem;
                    color: white;
                    position: relative;
                    z-index: 2;
                    box-shadow: 
                        0 8px 32px rgba(0, 112, 74, 0.3),
                        0 4px 16px rgba(0, 112, 74, 0.2);
                    animation: pulse 2s ease-in-out infinite;
                }
                
                @keyframes pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                }
                
                .icon-glow {
                    position: absolute;
                    top: -10px;
                    left: -10px;
                    right: -10px;
                    bottom: -10px;
                    background: radial-gradient(circle, rgba(39, 174, 96, 0.2) 0%, transparent 70%);
                    border-radius: 50%;
                    animation: glow 3s ease-in-out infinite alternate;
                }
                
                @keyframes glow {
                    0% { opacity: 0.5; transform: scale(1); }
                    100% { opacity: 1; transform: scale(1.1); }
                }
                
                .header-content h3 {
                    font-size: 2rem;
                    font-weight: 700;
                    color: #1a3c34;
                    margin: 0 0 0.5rem 0;
                    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                }
                
                .header-content p {
                    font-size: 1.1rem;
                    color: #4a5a5a;
                    margin: 0;
                    opacity: 0.9;
                    line-height: 1.6;
                }
                
                .modern-permission-form {
                    padding: 2rem;
                }
                
                .form-sections {
                    display: flex;
                    flex-direction: column;
                    gap: 2.5rem;
                }
                
                .form-section {
                    background: white;
                    border-radius: 16px;
                    padding: 2rem;
                    box-shadow: 
                        0 4px 20px rgba(0, 0, 0, 0.04),
                        0 2px 8px rgba(0, 0, 0, 0.02);
                    border: 1px solid rgba(0, 112, 74, 0.06);
                    transition: all 0.3s ease;
                }
                
                .form-section:hover {
                    box-shadow: 
                        0 8px 30px rgba(0, 0, 0, 0.08),
                        0 4px 12px rgba(0, 0, 0, 0.04);
                    transform: translateY(-2px);
                }
                
                .section-header {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    margin-bottom: 2rem;
                    padding-bottom: 1rem;
                    border-bottom: 2px solid #f0f9f6;
                }
                
                .section-number {
                    width: 40px;
                    height: 40px;
                    background: linear-gradient(135deg, #00704A, #27ae60);
                    color: white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 700;
                    font-size: 1.2rem;
                    box-shadow: 0 4px 12px rgba(0, 112, 74, 0.2);
                }
                
                .section-header h4 {
                    font-size: 1.4rem;
                    font-weight: 600;
                    color: #1a3c34;
                    margin: 0;
                }
                
                .section-header p {
                    font-size: 1rem;
                    color: #6b7280;
                    margin: 0;
                    opacity: 0.8;
                }
                
                .form-row {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 2rem;
                }
                
                .form-field {
                    position: relative;
                }
                
                .form-field.full-width {
                    grid-column: 1 / -1;
                }
                
                .field-label {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    font-weight: 600;
                    color: #1a3c34;
                    margin-bottom: 0.75rem;
                    font-size: 1rem;
                }
                
                .field-label i {
                    color: #00704A;
                    font-size: 1.1rem;
                }
                
                .select-wrapper,
                .input-wrapper,
                .textarea-wrapper {
                    position: relative;
                }
                
                .modern-select,
                .modern-input,
                .modern-textarea {
                    width: 100%;
                    padding: 1rem 1.2rem;
                    border: 2px solid #e5e7eb;
                    border-radius: 12px;
                    font-size: 1rem;
                    background: #fafbfc;
                    transition: all 0.3s ease;
                    color: #374151;
                }
                
                .modern-select:focus,
                .modern-input:focus,
                .modern-textarea:focus {
                    outline: none;
                    border-color: #00704A;
                    background: white;
                    box-shadow: 
                        0 0 0 4px rgba(0, 112, 74, 0.1),
                        0 4px 12px rgba(0, 112, 74, 0.1);
                }
                
                .modern-textarea {
                    min-height: 120px;
                    resize: vertical;
                    line-height: 1.6;
                }
                
                .select-arrow {
                    position: absolute;
                    right: 1rem;
                    top: 50%;
                    transform: translateY(-50%);
                    color: #00704A;
                    pointer-events: none;
                    transition: transform 0.3s ease;
                }
                
                .select-wrapper:focus-within .select-arrow {
                    transform: translateY(-50%) rotate(180deg);
                }
                
                .input-icon,
                .textarea-icon {
                    position: absolute;
                    right: 1rem;
                    top: 50%;
                    transform: translateY(-50%);
                    color: #9ca3af;
                    pointer-events: none;
                    transition: color 0.3s ease;
                }
                
                .textarea-icon {
                    top: 1.5rem;
                    transform: none;
                }
                
                .modern-input:focus + .input-icon,
                .modern-textarea:focus + .textarea-icon {
                    color: #00704A;
                }
                
                .field-hint {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    margin-top: 0.5rem;
                    font-size: 0.9rem;
                    color: #6b7280;
                    opacity: 0.8;
                }
                
                .field-hint i {
                    color: #f59e0b;
                }
                
                .submit-section {
                    background: linear-gradient(135deg, #f0f9f6 0%, #e8f5f0 100%);
                    border-radius: 16px;
                    padding: 2rem;
                    margin-top: 2rem;
                    text-align: center;
                }
                
                .submit-info {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 1rem;
                    margin-bottom: 2rem;
                    padding: 1rem 1.5rem;
                    background: rgba(255, 255, 255, 0.8);
                    border-radius: 12px;
                    border: 1px solid rgba(0, 112, 74, 0.1);
                }
                
                .info-icon {
                    color: #00704A;
                    font-size: 1.2rem;
                }
                
                .info-text {
                    color: #374151;
                    font-size: 0.95rem;
                    line-height: 1.5;
                }
                
                .submit-button {
                    position: relative;
                    background: linear-gradient(135deg, #00704A 0%, #27ae60 50%, #2ecc71 100%);
                    color: white;
                    border: none;
                    padding: 1.2rem 3rem;
                    border-radius: 12px;
                    font-size: 1.1rem;
                    font-weight: 600;
                    cursor: pointer;
                    overflow: hidden;
                    transition: all 0.3s ease;
                    box-shadow: 
                        0 8px 25px rgba(0, 112, 74, 0.3),
                        0 4px 12px rgba(0, 112, 74, 0.2);
                }
                
                .submit-button:hover {
                    transform: translateY(-2px);
                    box-shadow: 
                        0 12px 35px rgba(0, 112, 74, 0.4),
                        0 6px 16px rgba(0, 112, 74, 0.3);
                }
                
                .submit-button:active {
                    transform: translateY(0);
                }
                
                .button-content {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.75rem;
                    position: relative;
                    z-index: 2;
                }
                
                .button-glow {
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: linear-gradient(
                        45deg,
                        transparent,
                        rgba(255, 255, 255, 0.1),
                        transparent
                    );
                    transform: rotate(45deg);
                    animation: shine 3s infinite;
                }
                
                @keyframes shine {
                    0% { transform: translateX(-100%) rotate(45deg); }
                    100% { transform: translateX(100%) rotate(45deg); }
                }
                
                /* Responsive Design */
                @media (max-width: 768px) {
                    .permission-form-wrapper {
                        margin: 0 1rem;
                    }
                    
                    .form-header-section {
                        padding: 2rem 1.5rem 1.5rem;
                    }
                    
                    .header-icon {
                        width: 60px;
                        height: 60px;
                        font-size: 2rem;
                    }
                    
                    .header-content h3 {
                        font-size: 1.5rem;
                    }
                    
                    .modern-permission-form {
                        padding: 1.5rem;
                    }
                    
                    .form-section {
                        padding: 1.5rem;
                    }
                    
                    .form-row {
                        grid-template-columns: 1fr;
                        gap: 1.5rem;
                    }
                    
                    .submit-section {
                        padding: 1.5rem;
                    }
                    
                    .submit-info {
                        flex-direction: column;
                        text-align: center;
                        gap: 0.5rem;
                    }
                }
                
                /* Animation for form appearance */
                @keyframes formAppear {
                    0% {
                        opacity: 0;
                        transform: translateY(30px);
                    }
                    100% {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                .permission-form-wrapper {
                    animation: formAppear 0.6s ease-out;
                }
                
                /* Two-Column Layout Styles */
                .permission-layout {
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    gap: 2rem;
                    max-width: 1400px;
                    margin: 0 auto;
                }
                
                .permission-form-column {
                    min-width: 0;
                }
                
                .recent-requests-column {
                    min-width: 0;
                }
                
                /* Recent Requests Card Styles */
                .recent-requests-card {
                    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                    border-radius: 20px;
                    box-shadow: 
                        0 15px 40px rgba(0, 112, 74, 0.08),
                        0 6px 15px rgba(0, 112, 74, 0.06),
                        inset 0 1px 0 rgba(255, 255, 255, 0.8);
                    border: 1px solid rgba(0, 112, 74, 0.08);
                    overflow: hidden;
                    position: relative;
                    height: fit-content;
                    min-height: 500px;
                    max-height: 800px;
                    overflow-y: auto;
                }
                
                .recent-requests-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(90deg, #00704A, #27ae60, #2ecc71);
                    z-index: 1;
                }
                
                .recent-header {
                    background: linear-gradient(135deg, #f0f9f6 0%, #e8f5f0 100%);
                    padding: 1.5rem;
                    text-align: center;
                    position: relative;
                    overflow: hidden;
                }
                
                .recent-header::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: radial-gradient(circle, rgba(39, 174, 96, 0.03) 0%, transparent 70%);
                    animation: float 6s ease-in-out infinite;
                }
                
                .recent-icon {
                    width: 50px;
                    height: 50px;
                    background: linear-gradient(135deg, #00704A 0%, #27ae60 50%, #2ecc71 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.5rem;
                    color: white;
                    margin: 0 auto 1rem;
                    position: relative;
                    z-index: 2;
                    box-shadow: 
                        0 6px 20px rgba(0, 112, 74, 0.3),
                        0 3px 10px rgba(0, 112, 74, 0.2);
                }
                
                .recent-title h3 {
                    font-size: 1.3rem;
                    font-weight: 700;
                    color: #1a3c34;
                    margin: 0 0 0.3rem 0;
                    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                }
                
                .recent-title p {
                    font-size: 0.9rem;
                    color: #4a5a5a;
                    margin: 0;
                    opacity: 0.9;
                }
                
                .recent-content {
                    padding: 1.5rem;
                }
                
                .recent-requests-list {
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                }
                
                .recent-request-item {
                    background: white;
                    border-radius: 12px;
                    padding: 1rem;
                    box-shadow: 
                        0 2px 8px rgba(0, 0, 0, 0.04),
                        0 1px 3px rgba(0, 0, 0, 0.02);
                    border: 1px solid rgba(0, 112, 74, 0.06);
                    transition: all 0.3s ease;
                }
                
                .recent-request-item:hover {
                    box-shadow: 
                        0 4px 12px rgba(0, 0, 0, 0.08),
                        0 2px 6px rgba(0, 0, 0, 0.04);
                    transform: translateY(-2px);
                }
                
                .request-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 0.8rem;
                }
                
                .request-type-badge {
                    font-size: 1.2rem;
                    opacity: 0.8;
                }
                
                .request-status {
                    flex-shrink: 0;
                }
                
                .request-content {
                    margin-bottom: 0.8rem;
                }
                
                .request-text {
                    font-size: 0.9rem;
                    color: #374151;
                    line-height: 1.4;
                    margin-bottom: 0.5rem;
                }
                
                .request-date {
                    display: flex;
                    align-items: center;
                    gap: 0.3rem;
                    font-size: 0.8rem;
                    color: #6b7280;
                }
                
                .request-date i {
                    font-size: 0.7rem;
                }
                
                .request-response {
                    display: flex;
                    align-items: flex-start;
                    gap: 0.5rem;
                    padding: 0.5rem;
                    background: rgba(0, 112, 74, 0.05);
                    border-radius: 8px;
                    border-left: 3px solid #00704A;
                    font-size: 0.8rem;
                    color: #374151;
                    line-height: 1.3;
                }
                
                .request-response i {
                    color: #00704A;
                    margin-top: 0.1rem;
                    flex-shrink: 0;
                }
                
                .view-all-section {
                    text-align: center;
                    margin-top: 1.5rem;
                    padding-top: 1rem;
                    border-top: 1px solid rgba(0, 112, 74, 0.1);
                }
                
                .view-all-link {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    color: #00704A;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 0.9rem;
                    padding: 0.5rem 1rem;
                    border-radius: 8px;
                    transition: all 0.3s ease;
                    background: rgba(0, 112, 74, 0.05);
                }
                
                .view-all-link:hover {
                    background: rgba(0, 112, 74, 0.1);
                    transform: translateY(-1px);
                    text-decoration: none;
                    color: #00704A;
                }
                
                .no-requests {
                    text-align: center;
                    padding: 2rem 1rem;
                    color: #6b7280;
                }
                
                .no-requests-icon {
                    font-size: 3rem;
                    color: #d1d5db;
                    margin-bottom: 1rem;
                }
                
                .no-requests h4 {
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: #374151;
                    margin: 0 0 0.5rem 0;
                }
                
                .no-requests p {
                    font-size: 0.9rem;
                    margin: 0;
                    opacity: 0.8;
                }
                
                /* Responsive Design for Two-Column Layout */
                @media (max-width: 1024px) {
                    .permission-layout {
                        grid-template-columns: 1fr;
                        gap: 1.5rem;
                    }
                    
                    .recent-requests-card {
                        max-height: none;
                        order: -1;
                    }
                }
                
                @media (max-width: 768px) {
                    .permission-layout {
                        margin: 0 1rem;
                    }
                    
                    .recent-header {
                        padding: 1rem;
                    }
                    
                    .recent-icon {
                        width: 40px;
                        height: 40px;
                        font-size: 1.2rem;
                    }
                    
                    .recent-title h3 {
                        font-size: 1.1rem;
                    }
                    
                    .recent-content {
                        padding: 1rem;
                    }
                    
                    .recent-request-item {
                        padding: 0.8rem;
                    }
                }
                </style>
                <!-- Previous Requests Table has been removed as requested. -->
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
        
        // Permission request form validation and real-time updates
        document.addEventListener('DOMContentLoaded', function() {
            const permissionForm = document.querySelector('form[name="permission_request"]') || 
                                 document.querySelector('form[method="POST"]');
            
            // Real-time status update checker
            function checkForStatusUpdates() {
                // Get all permission request IDs from the table
                const requestRows = document.querySelectorAll('tbody tr');
                const requestIds = [];
                
                requestRows.forEach(row => {
                    const statusBadge = row.querySelector('.status-badge');
                    if (statusBadge && statusBadge.classList.contains('status-pending')) {
                        // Extract request ID from the row
                        const requestId = row.getAttribute('data-request-id');
                        if (requestId) {
                            requestIds.push(requestId);
                        }
                    }
                });
                
                if (requestIds.length > 0) {
                    console.log('Checking for updates on requests:', requestIds);
                    
                    // Check for updates
                    fetch('check_permission_updates.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            request_ids: requestIds
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Update check response:', data);
                        if (data.success && data.updates && data.updates.length > 0) {
                            console.log('Found updates:', data.updates);
                            data.updates.forEach(update => {
                                updateRequestStatus(update);
                            });
                            
                            // Show notification
                            showNotification('Some of your permission requests have been updated!', 'success');
                        }
                    })
                    .catch(error => {
                        console.error('Status check failed:', error);
                    });
                }
            }
            
            // Update request status in the UI
            function updateRequestStatus(update) {
                const row = document.querySelector(`tr[data-request-id="${update.id}"]`);
                if (row) {
                    const statusCell = row.querySelector('td:last-child');
                    const statusBadge = statusCell.querySelector('.status-badge');
                    
                    if (statusBadge) {
                        // Update status badge
                        statusBadge.className = `status-badge status-${update.status} popover-status`;
                        
                        // Update status icon and text
                        let statusIcon = '';
                        let statusText = update.status.charAt(0).toUpperCase() + update.status.slice(1);
                        
                        switch (update.status) {
                            case 'pending':
                                statusIcon = '<i class="fas fa-clock"></i> ';
                                break;
                            case 'approved':
                                statusIcon = '<i class="fas fa-check-circle"></i> ';
                                break;
                            case 'rejected':
                                statusIcon = '<i class="fas fa-times-circle"></i> ';
                                break;
                        }
                        
                        statusBadge.innerHTML = statusIcon + statusText;
                        
                        // Update tooltip
                        let tooltip = '';
                        if (update.status === 'pending') {
                            tooltip = "⏳ **PENDING**\n\nYour request is currently under review by the school administration.\n\nPlease wait for a response.";
                        } else if (update.status === 'approved') {
                            tooltip = update.response_comment ? 
                                "✅ **APPROVED**\n\n**Admin Response:**\n" + update.response_comment :
                                "✅ **APPROVED**\n\nYour request has been approved!";
                        } else if (update.status === 'rejected') {
                            tooltip = update.response_comment ? 
                                "❌ **REJECTED**\n\n**Admin Response:**\n" + update.response_comment :
                                "❌ **REJECTED**\n\nYour request has been rejected.";
                        }
                        
                        statusBadge.setAttribute('data-tooltip', tooltip);
                        
                        // Add info icon if there's a response comment
                        if (update.response_comment && (update.status === 'approved' || update.status === 'rejected')) {
                            statusBadge.innerHTML += '<i class="fas fa-info-circle" style="margin-left: 5px; font-size: 0.8em; opacity: 0.7;"></i>';
                        }
                        
                        // Add animation
                        statusBadge.style.animation = 'statusUpdate 0.5s ease-in-out';
                        setTimeout(() => {
                            statusBadge.style.animation = '';
                        }, 500);
                    }
                }
            }
            
            // Show notification function
            function showNotification(message, type = 'success') {
                const notification = document.createElement('div');
                notification.className = `alert alert-${type}`;
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    padding: 15px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    animation: slideInRight 0.3s ease-out;
                `;
                notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.animation = 'slideOutRight 0.3s ease-in';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }, 5000);
            }
            
            // Start checking for updates if there are pending requests
            const pendingRequests = document.querySelectorAll('.status-badge.status-pending');
            if (pendingRequests.length > 0) {
                // Check immediately
                setTimeout(checkForStatusUpdates, 1000);
                
                // Then check every 30 seconds
                setInterval(checkForStatusUpdates, 30000);
            }
            
            // Manual refresh button
            const refreshButton = document.getElementById('refreshRequests');
            if (refreshButton) {
                refreshButton.addEventListener('click', function() {
                    // Add loading state
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
                    this.disabled = true;
                    
                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                });
            }
            
            if (permissionForm) {
                permissionForm.addEventListener('submit', function(e) {
                    const requestType = document.getElementById('request_type');
                    const studentSelect = document.getElementById('student_select');
                    const startDate = document.getElementById('start_date');
                    const endDate = document.getElementById('end_date');
                    const requestText = document.getElementById('request_text');
                    
                    let isValid = true;
                    
                    // Clear previous error states
                    [requestType, studentSelect, startDate, endDate, requestText].forEach(field => {
                        if (field) {
                            field.style.borderColor = '';
                            field.classList.remove('error');
                        }
                    });
                    
                    // Validate request type
                    if (!requestType.value) {
                        requestType.style.borderColor = '#dc3545';
                        requestType.classList.add('error');
                        isValid = false;
                    }
                    
                    // Validate student selection
                    if (!studentSelect.value) {
                        studentSelect.style.borderColor = '#dc3545';
                        studentSelect.classList.add('error');
                        isValid = false;
                    }
                    
                    // Validate dates
                    if (!startDate.value) {
                        startDate.style.borderColor = '#dc3545';
                        startDate.classList.add('error');
                        isValid = false;
                    }
                    
                    if (!endDate.value) {
                        endDate.style.borderColor = '#dc3545';
                        endDate.classList.add('error');
                        isValid = false;
                    }
                    
                    // Validate that end date is after start date
                    if (startDate.value && endDate.value && new Date(endDate.value) <= new Date(startDate.value)) {
                        endDate.style.borderColor = '#dc3545';
                        endDate.classList.add('error');
                        alert('End date must be after start date');
                        isValid = false;
                    }
                    
                    // Validate request text
                    if (!requestText.value.trim()) {
                        requestText.style.borderColor = '#dc3545';
                        requestText.classList.add('error');
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fill in all required fields correctly.');
                    }
                });
            }
            
            // Auto-set end date to start date + 1 day if not set
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            if (startDateInput && endDateInput) {
                startDateInput.addEventListener('change', function() {
                    if (this.value && !endDateInput.value) {
                        const startDate = new Date(this.value);
                        startDate.setDate(startDate.getDate() + 1);
                        endDateInput.value = startDate.toISOString().slice(0, 16);
                    }
                });
            }
        });
        
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

    <!-- Announcements Modal -->
    <div class="modal-announcements-bg" id="announcementsModalBg">
        <div class="modal-announcements">
            <span class="close-modal" onclick="closeAnnouncementsModal()">&times;</span>
            <h2 style="margin-bottom: 1.2rem; color: var(--primary-color);"><i class="fas fa-bullhorn"></i> School Announcements</h2>
            <div id="announcementsList">
                <div style="text-align:center; color:#888; padding:2rem 0;">
                    <i class="fas fa-spinner fa-spin"></i> Loading announcements...
                </div>
            </div>
        </div>
    </div>
    <script>
        function openAnnouncementsModal() {
            document.getElementById('announcementsModalBg').style.display = 'flex';
            fetchAnnouncements();
        }
        function closeAnnouncementsModal() {
            document.getElementById('announcementsModalBg').style.display = 'none';
        }
        function fetchAnnouncements() {
            const list = document.getElementById('announcementsList');
            list.innerHTML = '<div style="text-align:center; color:#888; padding:2rem 0;"><i class="fas fa-spinner fa-spin"></i> Loading announcements...</div>';
            fetch('fetch_announcements.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.announcements.length > 0) {
                        list.innerHTML = data.announcements.map(a => `
                            <div class="announcement-item">
                                <div class="announcement-title">${a.title}</div>
                                <div class="announcement-meta">
                                    <span>${a.date}</span> &bull; 
                                    <span class="announcement-priority ${a.priority}">${a.priority.charAt(0).toUpperCase() + a.priority.slice(1)}</span>
                                </div>
                                <div class="announcement-content">${a.content}</div>
                                ${a.attachment ? `<div style=\"margin-top:0.5rem;\"><a href=\"../uploads/announcements/${a.attachment}\" target=\"_blank\" class=\"btn btn-sm\" style=\"background:#2563eb;color:#fff;padding:0.4rem 1rem;border-radius:6px;text-decoration:none;display:inline-block;margin-top:0.3rem;\"><i class=\"fas fa-paperclip\"></i> View Attachment</a></div>` : ''}
                            </div>
                        `).join('');
                    } else {
                        list.innerHTML = '<div style="text-align:center; color:#888; padding:2rem 0;">No announcements found.</div>';
                    }
                })
                .catch(() => {
                    list.innerHTML = '<div style="text-align:center; color:#e53e3e; padding:2rem 0;">Failed to load announcements.</div>';
                });
        }
    </script>
</body>
</html></html>
