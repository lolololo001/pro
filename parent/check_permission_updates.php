<?php
/**
 * Check for permission status updates
 * This script is called by AJAX to check if any permission requests have been updated
 */

require_once '../config/config.php';

// Start session
session_start();

// Check if user is logged in as parent
if (!isset($_SESSION['parent_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$parentId = $_SESSION['parent_id'];
$requestIds = $input['request_ids'] ?? [];

if (empty($requestIds)) {
    echo json_encode(['updates' => []]);
    exit;
}

try {
    $conn = getDbConnection();
    
    // Convert request IDs to comma-separated string for SQL
    $requestIdsStr = implode(',', array_map('intval', $requestIds));
    
    // Check for updates on the specified requests
    $query = "SELECT pr.id, pr.status, pr.response_comment, pr.updated_at,
                     COALESCE(s.first_name, '') as first_name, 
                     COALESCE(s.last_name, '') as last_name, 
                     COALESCE(s.admission_number, '') as admission_number,
                     COALESCE(sa.full_name, 'School Admin') as admin_name
              FROM permission_requests pr
              LEFT JOIN students s ON pr.student_id = s.id
              LEFT JOIN school_admins sa ON pr.responded_by = sa.id
              WHERE pr.id IN ($requestIdsStr) 
              AND pr.parent_id = ?
              AND pr.status IN ('approved', 'rejected')
              AND pr.updated_at IS NOT NULL
              ORDER BY pr.updated_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $parentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Debug logging
    error_log("Permission update check - Parent ID: $parentId, Request IDs: " . implode(',', $requestIds));
    error_log("Query executed, found " . $result->num_rows . " updates");
    
    $updates = [];
    while ($row = $result->fetch_assoc()) {
        $updates[] = [
            'id' => $row['id'],
            'status' => $row['status'],
            'response_comment' => $row['response_comment'],
            'updated_at' => $row['updated_at'],
            'admin_name' => $row['admin_name'],
            'student_name' => trim($row['first_name'] . ' ' . $row['last_name'])
        ];
        error_log("Update found - ID: {$row['id']}, Status: {$row['status']}");
    }
    
    $stmt->close();
    $conn->close();
    
    // Return updates
    echo json_encode([
        'success' => true,
        'updates' => $updates,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Permission update check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error occurred',
        'message' => $e->getMessage()
    ]);
}
?> 