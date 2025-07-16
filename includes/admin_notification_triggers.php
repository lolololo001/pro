<?php
/**
 * Admin notification triggers for feedback and permission requests
 */

require_once '../config/config.php';

/**
 * Trigger notification when parent submits feedback
 */
function triggerFeedbackNotification($parent_id, $feedback_id, $message) {
    try {
        $conn = getDbConnection();
        
        // Get parent and student information
        $stmt = $conn->prepare("
            SELECT p.first_name as parent_first_name, p.last_name as parent_last_name, 
                   s.first_name as student_first_name, s.last_name as student_last_name,
                   s.school_id
            FROM parents p
            INNER JOIN student_parent sp ON p.id = sp.parent_id
            INNER JOIN students s ON sp.student_id = s.id
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $parent_name = $row['parent_first_name'] . ' ' . $row['parent_last_name'];
            $student_name = $row['student_first_name'] . ' ' . $row['student_last_name'];
            $school_id = $row['school_id'];
            
            // Create notification message
            $notification_message = "New feedback received from $parent_name (parent of $student_name): " . substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '');
            
            // Insert admin notification
            $notif_stmt = $conn->prepare("
                INSERT INTO admin_notifications 
                (school_id, type, title, message, reference_table, reference_id, created_at) 
                VALUES (?, 'feedback', 'New Parent Feedback', ?, 'parent_feedback', ?, NOW())
            ");
            $notif_stmt->bind_param("isi", $school_id, $notification_message, $feedback_id);
            $success = $notif_stmt->execute();
            $notif_stmt->close();
            
            $stmt->close();
            $conn->close();
            return $success;
        }
        
        $stmt->close();
        $conn->close();
        return false;
        
    } catch (Exception $e) {
        error_log("Admin feedback notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification when parent submits permission request
 */
function triggerPermissionRequestNotification($parent_id, $student_id, $request_id, $request_type, $reason) {
    try {
        $conn = getDbConnection();
        
        // Get parent and student information
        $stmt = $conn->prepare("
            SELECT p.first_name as parent_first_name, p.last_name as parent_last_name, 
                   s.first_name as student_first_name, s.last_name as student_last_name,
                   s.school_id, c.class_name
            FROM parents p
            INNER JOIN students s ON s.id = ?
            INNER JOIN student_parent sp ON s.id = sp.student_id AND sp.parent_id = p.id
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE p.id = ?
        ");
        $stmt->bind_param("ii", $student_id, $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $parent_name = $row['parent_first_name'] . ' ' . $row['parent_last_name'];
            $student_name = $row['student_first_name'] . ' ' . $row['student_last_name'];
            $school_id = $row['school_id'];
            $class_name = $row['class_name'] ?? 'Unknown Class';
            
            // Create notification message
            $type_display = ucfirst(str_replace('_', ' ', $request_type));
            $notification_message = "New permission request from $parent_name for $student_name ($class_name) - $type_display: " . substr($reason, 0, 100) . (strlen($reason) > 100 ? '...' : '');
            
            // Insert admin notification
            $notif_stmt = $conn->prepare("
                INSERT INTO admin_notifications 
                (school_id, type, title, message, reference_table, reference_id, created_at) 
                VALUES (?, 'permission_request', 'New Permission Request', ?, 'permission_requests', ?, NOW())
            ");
            $notif_stmt->bind_param("isi", $school_id, $notification_message, $request_id);
            $success = $notif_stmt->execute();
            $notif_stmt->close();
            
            $stmt->close();
            $conn->close();
            return $success;
        }
        
        $stmt->close();
        $conn->close();
        return false;
        
    } catch (Exception $e) {
        error_log("Admin permission request notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification when permission request status is updated
 */
function triggerPermissionStatusUpdateNotification($request_id, $new_status, $admin_response = '') {
    try {
        $conn = getDbConnection();
        
        // Get request and related information
        $stmt = $conn->prepare("
            SELECT pr.*, p.first_name as parent_first_name, p.last_name as parent_last_name,
                   s.first_name as student_first_name, s.last_name as student_last_name,
                   s.school_id
            FROM permission_requests pr
            INNER JOIN students s ON pr.student_id = s.id
            INNER JOIN student_parent sp ON s.id = sp.student_id
            INNER JOIN parents p ON sp.parent_id = p.id
            WHERE pr.id = ?
        ");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $parent_name = $row['parent_first_name'] . ' ' . $row['parent_last_name'];
            $student_name = $row['student_first_name'] . ' ' . $row['student_last_name'];
            $school_id = $row['school_id'];
            $request_type = ucfirst(str_replace('_', ' ', $row['request_type']));
            
            // Create notification message
            $status_display = ucfirst($new_status);
            $notification_message = "Permission request $status_display: $request_type for $student_name (requested by $parent_name)";
            if (!empty($admin_response)) {
                $notification_message .= " - Response: " . substr($admin_response, 0, 50) . (strlen($admin_response) > 50 ? '...' : '');
            }
            
            // Insert admin notification for tracking
            $notif_stmt = $conn->prepare("
                INSERT INTO admin_notifications 
                (school_id, type, title, message, reference_table, reference_id, created_at) 
                VALUES (?, 'permission_update', 'Permission Request Updated', ?, 'permission_requests', ?, NOW())
            ");
            $notif_stmt->bind_param("isi", $school_id, $notification_message, $request_id);
            $success = $notif_stmt->execute();
            $notif_stmt->close();
            
            $stmt->close();
            $conn->close();
            return $success;
        }
        
        $stmt->close();
        $conn->close();
        return false;
        
    } catch (Exception $e) {
        error_log("Admin permission status update notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notification count for admin dashboard
 */
function getAdminNotificationCount($school_id) {
    try {
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_notifications WHERE school_id = ? AND is_read = 0");
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        $stmt->close();
        $conn->close();
        return $count;
        
    } catch (Exception $e) {
        error_log("Error getting admin notification count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get recent notifications for admin dashboard
 */
function getRecentAdminNotifications($school_id, $limit = 10) {
    try {
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            SELECT id, type, title, message, reference_table, reference_id, is_read, created_at,
                   CASE 
                       WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_at, NOW()), ' minutes ago')
                       WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN CONCAT(TIMESTAMPDIFF(HOUR, created_at, NOW()), ' hours ago')
                       WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK) THEN CONCAT(TIMESTAMPDIFF(DAY, created_at, NOW()), ' days ago')
                       ELSE DATE_FORMAT(created_at, '%M %d, %Y')
                   END as time_ago
            FROM admin_notifications 
            WHERE school_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bind_param("ii", $school_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        return $notifications;
        
    } catch (Exception $e) {
        error_log("Error getting admin notifications: " . $e->getMessage());
        return [];
    }
}
?>
