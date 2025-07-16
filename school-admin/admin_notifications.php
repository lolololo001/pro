<?php
/**
 * Admin Notification System Functions
 */

require_once '../config/config.php';

/**
 * Create admin notifications table if it doesn't exist
 */
function createAdminNotificationsTable() {
    $conn = getDbConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('permission_request', 'feedback', 'student_registration', 'general') DEFAULT 'general',
        reference_table VARCHAR(50) NULL,
        reference_id INT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_school_read (school_id, is_read),
        INDEX idx_created (created_at)
    )";
    
    $result = $conn->query($sql);
    $conn->close();
    return $result;
}

/**
 * Create notification for school admin
 */
function createAdminNotification($school_id, $title, $message, $type = 'general', $reference_table = null, $reference_id = null) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("INSERT INTO admin_notifications (school_id, title, message, type, reference_table, reference_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $school_id, $title, $message, $type, $reference_table, $reference_id);
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

/**
 * Get unread notification count for school admin
 */
function getAdminNotificationCount($school_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_notifications WHERE school_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $count = $row['count'];
    $stmt->close();
    $conn->close();
    
    return $count;
}

/**
 * Get notifications for school admin
 */
function getAdminNotifications($school_id, $limit = 20, $offset = 0) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT id, title, message, type, reference_table, reference_id, is_read, created_at 
                           FROM admin_notifications 
                           WHERE school_id = ? 
                           ORDER BY created_at DESC 
                           LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $school_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['time_ago'] = timeAgo($row['created_at']);
        $row['icon'] = getNotificationIcon($row['type']);
        $row['color'] = getNotificationColor($row['type']);
        $notifications[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $notifications;
}

/**
 * Mark notification as read
 */
function markAdminNotificationAsRead($notification_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = TRUE WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

/**
 * Mark all notifications as read
 */
function markAllAdminNotificationsAsRead($school_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = TRUE WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

/**
 * Delete notification
 */
function deleteAdminNotification($notification_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("DELETE FROM admin_notifications WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

/**
 * Get notification icon based on type
 */
function getNotificationIcon($type) {
    $icons = [
        'permission_request' => 'fas fa-clipboard-list',
        'feedback' => 'fas fa-comment-dots',
        'student_registration' => 'fas fa-user-plus',
        'general' => 'fas fa-bell'
    ];
    
    return $icons[$type] ?? 'fas fa-bell';
}

/**
 * Get notification color based on type
 */
function getNotificationColor($type) {
    $colors = [
        'permission_request' => '#f59e0b',
        'feedback' => '#3b82f6',
        'student_registration' => '#8b5cf6',
        'general' => '#6b7280'
    ];
    
    return $colors[$type] ?? '#6b7280';
}

/**
 * Time ago helper function
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    if ($time < 31536000) return floor($time/2592000) . 'mo ago';
    return floor($time/31536000) . 'y ago';
}

// Initialize table on first load
createAdminNotificationsTable();
?>
