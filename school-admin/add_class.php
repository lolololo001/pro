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

// Initialize response
$response = [
    'success' => false,
    'message' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $class_name = trim($_POST['class_name'] ?? '');
    $grade_level = trim($_POST['grade_level'] ?? null); // Optional field
    $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;        

    if (empty($class_name)) {
        $_SESSION['class_error'] = 'Class name is a required field.';
        // Check if the request came from dashboard and redirect accordingly
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (strpos($referer, 'dashboard.php') !== false) {
            header('Location: dashboard.php');
        } else {
            header('Location: classes.php');
        }
        exit;
    }
    
    try {
        // Get database connection
        $conn = getDbConnection();
        
        // Check if classes table exists
        $result = $conn->query("SHOW TABLES LIKE 'classes'");
        if ($result->num_rows == 0) {
            // Create classes table if it doesn't exist
            $conn->query("CREATE TABLE IF NOT EXISTS classes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                school_id INT NOT NULL,
                class_name VARCHAR(50) NOT NULL,
                grade_level VARCHAR(20),
                teacher_id INT,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
            )");
        }
        
        // Check if class already exists in this school
        $stmt = $conn->prepare("SELECT id FROM classes WHERE class_name = ? AND (grade_level = ? OR grade_level IS NULL) AND school_id = ?");
        $stmt->bind_param('ssi', $class_name, $grade_level, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['class_error'] = 'A class with this name and grade level already exists.';
            // Check if the request came from dashboard and redirect accordingly
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, 'dashboard.php') !== false) {
                header('Location: dashboard.php');
            } else {
                header('Location: classes.php');
            }
            exit;
        }
        
        // Insert new class
        if ($teacher_id) {
            $stmt = $conn->prepare("INSERT INTO classes (school_id, class_name, grade_level, teacher_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('issi', $school_id, $class_name, $grade_level, $teacher_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO classes (school_id, class_name, grade_level) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $school_id, $class_name, $grade_level);
        }
        
        if ($stmt->execute()) {
            $_SESSION['class_success'] = 'Class added successfully!';
        } else {
            $_SESSION['class_error'] = 'Failed to add class: ' . $conn->error;
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $_SESSION['class_error'] = 'System error: ' . $e->getMessage();
    }
    
    // Check if the request came from dashboard and redirect accordingly
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referer, 'dashboard.php') !== false) {
        header('Location: dashboard.php');
    } else {
        header('Location: classes.php');
    }
    exit;
} else {
    // Check if the request came from dashboard and redirect accordingly
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referer, 'dashboard.php') !== false) {
        header('Location: dashboard.php');
    } else {
        header('Location: classes.php');
    }
    exit;
}