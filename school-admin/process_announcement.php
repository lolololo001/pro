<?php
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
    $_SESSION['announcement_error'] = "Error: School ID not found in session.";
    header('Location: dashboard.php');
    exit;
}

// Validate and process the announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $publish_date = trim($_POST['publish_date'] ?? date('Y-m-d'));
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $priority = trim($_POST['priority'] ?? 'medium');    // Set header to return JSON
    header('Content-Type: application/json');

    // Validate required inputs
    if (empty($title) || empty($content)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please fill in all required fields.'
        ]);
        exit;
    }

    // Get database connection
    $conn = getDbConnection();

    // Check database connection
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed.'
        ]);
        exit;
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        // Create announcements table if it doesn't exist with target_group
        $conn->query("CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            publish_date DATE NOT NULL,
            expiry_date DATE,
            priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
            target_group ENUM('all', 'parents', 'students', 'teachers', 'staff') NOT NULL DEFAULT 'all',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
        )");

        // Insert the announcement with target_group
        $stmt = $conn->prepare("INSERT INTO announcements (school_id, title, content, publish_date, expiry_date, priority, target_group) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $expiry = empty($expiry_date) ? null : $expiry_date;
        $target_group = trim($_POST['target_group'] ?? 'all');
        $stmt->bind_param('issssss', $school_id, $title, $content, $publish_date, $expiry, $priority, $target_group);
        if (!$stmt->execute()) {
            throw new Exception("Failed to publish announcement.");
        }
        $stmt->close();
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Announcement published successfully.'
        ]);
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            $conn->rollback();
        }
        error_log("Error processing announcement: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while processing your announcement: ' . $e->getMessage()
        ]);
        exit;
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}
