<?php
/**
 * School Admin Notifications API
 */

session_start();
require_once 'admin_notifications.php';

// Check if school admin is logged in
if (!isset($_SESSION['school_admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$school_id = $_SESSION['school_admin_school_id'] ?? 0;
if (!$school_id) {
    http_response_code(400);
    echo json_encode(['error' => 'School ID not found']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'get_count':
        $count = getAdminNotificationCount($school_id);
        echo json_encode(['count' => $count]);
        break;
        
    case 'get_notifications':
        $limit = intval($_GET['limit'] ?? 20);
        $offset = intval($_GET['offset'] ?? 0);
        $notifications = getAdminNotifications($school_id, $limit, $offset);
        echo json_encode(['notifications' => $notifications]);
        break;
        
    case 'mark_read':
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id > 0) {
            $result = markAdminNotificationAsRead($notification_id);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
        }
        break;
        
    case 'mark_all_read':
        $result = markAllAdminNotificationsAsRead($school_id);
        echo json_encode(['success' => $result]);
        break;
        
    case 'delete':
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id > 0) {
            $result = deleteAdminNotification($notification_id);
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
            markAdminNotificationAsRead($notification_id);
        }
        
        // Generate redirect URL based on reference
        $redirect_url = 'dashboard.php';
        
        switch ($reference_table) {
            case 'permission_requests':
                $redirect_url = 'permissions.php?highlight=' . $reference_id;
                break;
            case 'parent_feedback':
                $redirect_url = 'feedback.php?highlight=' . $reference_id;
                break;
            case 'students':
                $redirect_url = 'students.php?highlight=' . $reference_id;
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
