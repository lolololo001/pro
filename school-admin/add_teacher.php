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
    $teacher_name = trim($_POST['teacher_name'] ?? '');
    $teacher_email = trim($_POST['teacher_email'] ?? '');
    $teacher_phone = trim($_POST['teacher_phone'] ?? '');
    $teacher_subject = trim($_POST['teacher_subject'] ?? '');
    $teacher_qualification = trim($_POST['teacher_qualification'] ?? '');
    $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    
    if (empty($teacher_name) || empty($teacher_email) || empty($teacher_phone) || empty($department_id)) {
        $_SESSION['teacher_error'] = 'All required fields must be filled.';
        header('Location: dashboard.php');
        exit;
    }
    
    // Validate email format
    if (!filter_var($teacher_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['teacher_error'] = 'Please enter a valid email address.';
        header('Location: dashboard.php');
        exit;
    }
    
    try {
        // Get database connection
        $conn = getDbConnection();
        
        // Check if teachers table exists
        $result = $conn->query("SHOW TABLES LIKE 'teachers'");
        if ($result->num_rows == 0) {
            // Create teachers table if it doesn't exist
            $conn->query("CREATE TABLE IF NOT EXISTS teachers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                school_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                subject VARCHAR(50),
                qualification VARCHAR(255),
                department_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
                FOREIGN KEY (department_id) REFERENCES departments(dep_id) ON DELETE SET NULL
            )");
            
            // Check if department_id column exists
            $result = $conn->query("SHOW COLUMNS FROM teachers LIKE 'department_id'");
            if ($result->num_rows == 0) {
                // Add department_id column if it doesn't exist
                $conn->query("ALTER TABLE teachers ADD COLUMN department_id INT NULL, ADD FOREIGN KEY (department_id) REFERENCES departments(dep_id) ON DELETE SET NULL");
            }
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM teachers WHERE email = ? AND school_id = ?");
        $stmt->bind_param('si', $teacher_email, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['teacher_error'] = 'A teacher with this email already exists.';
            header('Location: dashboard.php');
            exit;
        }
        
        // Insert new teacher
        $stmt = $conn->prepare("INSERT INTO teachers (school_id, name, email, phone, subject, qualification, department_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssssi', $school_id, $teacher_name, $teacher_email, $teacher_phone, $teacher_subject, $teacher_qualification, $department_id);
        
        if ($stmt->execute()) {
            $_SESSION['teacher_success'] = 'Teacher added successfully!';
            header('Location: dashboard.php');
            exit;
        } else {
            $_SESSION['teacher_error'] = 'Failed to add teacher: ' . $conn->error;
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $_SESSION['teacher_error'] = 'System error: ' . $e->getMessage();
    }
    
    // Redirect back to dashboard
    header('Location: dashboard.php');
    exit;
} else {
    // Not a POST request, redirect to dashboard
    header('Location: dashboard.php');
    exit;
}