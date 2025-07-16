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
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get school_id from session
$school_id = $_SESSION['school_admin_school_id'] ?? 0;
if (!$school_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'School ID not found']);
    exit;
}

// Check if module ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Module ID is required']);
    exit;
}

$module_id = intval($_GET['id']);

try {
    $conn = getDbConnection();
    
    // Get module data
    $stmt = $conn->prepare("SELECT * FROM modules WHERE module_id = ? AND school_id = ?");
    $stmt->bind_param('ii', $module_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $module = $result->fetch_assoc();
        
        // Return module data as JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'module' => $module
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Module not found']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 