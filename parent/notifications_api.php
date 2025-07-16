<?php
/**
 * Parent Notifications API
 */

session_start();
require_once 'parent_notifications.php';

// Check if parent is logged in
if (!isset($_SESSION['parent_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$parent_id = $_SESSION['parent_id'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'get_count':
        $count = getParentNotificationCount($parent_id);
        echo json_encode(['count' => $count]);
        break;
        
    case 'get_notifications':
        $limit = intval($_GET['limit'] ?? 20);
        $offset = intval($_GET['offset'] ?? 0);
        $notifications = getParentNotifications($parent_id, $limit, $offset);
        echo json_encode(['notifications' => $notifications]);
        break;
        
    case 'mark_read':
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id > 0) {
            $result = markParentNotificationAsRead($notification_id);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
        }
        break;
        
    case 'mark_all_read':
        $result = markAllParentNotificationsAsRead($parent_id);
        echo json_encode(['success' => $result]);
        break;
        
    case 'delete':
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id > 0) {
            $result = deleteParentNotification($notification_id);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
        }
        break;
        
    case 'get_redirect_url':
        $notification_id = intval($_GET['notification_id'] ?? 0);
        $reference_table = $_GET['reference_table'] ?? '';
        $reference_id = intval($_GET['reference_id'] ?? 0);
        
        // Mark as read first
        if ($notification_id > 0) {
            markParentNotificationAsRead($notification_id);
        }
        
        // Generate redirect URL based on reference
        $redirect_url = 'dashboard.php';
        
        switch ($reference_table) {
            case 'permission_requests':
                $redirect_url = 'dashboard.php?page=permissions&highlight=' . $reference_id;
                break;
            case 'grades':
                $redirect_url = 'dashboard.php?page=academics&highlight=' . $reference_id;
                break;
            case 'attendance':
                $redirect_url = 'dashboard.php?page=attendance&highlight=' . $reference_id;
                break;
            case 'fees':
                $redirect_url = 'dashboard.php?page=fees&highlight=' . $reference_id;
                break;
            case 'announcements':
                $redirect_url = 'dashboard.php?page=announcements&highlight=' . $reference_id;
                break;
            default:
                $redirect_url = 'dashboard.php';
        }
        
        echo json_encode(['redirect_url' => $redirect_url]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
?>
