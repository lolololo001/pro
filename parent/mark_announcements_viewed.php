<?php
/**
 * Mark announcements as viewed by parent
 */

require_once '../config/config.php';
require_once '../includes/announcement_helpers.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['parent_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$parent_id = $_SESSION['parent_id'];

try {
    // Mark all current announcements as viewed
    $result = markAnnouncementsAsViewed($parent_id);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Announcements marked as viewed.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark announcements as viewed.']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
