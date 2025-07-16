<?php
/**
 * Parent Notification System Functions
 */

require_once '../config/config.php';

/**
 * Create parent notifications table if it doesn't exist
 */
function createParentNotificationsTable() {
    $conn = getDbConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS parent_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('permission_response', 'academic_update', 'attendance_update', 'fee_reminder', 'announcement', 'general') DEFAULT 'general',
        reference_table VARCHAR(50) NULL,
        reference_id INT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_parent_read (parent_id, is_read),
        INDEX idx_created (created_at)
    )";
    
    $result = $conn->query($sql);
    $conn->close();
    return $result;
}

/**
 * Create notification for parent
 */
function createParentNotification($parent_id, $title, $message, $type = 'general', $reference_table = null, $reference_id = null) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("INSERT INTO parent_notifications (parent_id, title, message, type, reference_table, reference_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $parent_id, $title, $message, $type, $reference_table, $reference_id);
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

/**
 * Get unread notification count for parent
 */
function getParentNotificationCount($parent_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM parent_notifications WHERE parent_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $count = $row['count'];
    $stmt->close();
    $conn->close();
    
    return $count;
}

/**
 * Get notifications for parent
 */
function getParentNotifications($parent_id, $limit = 20, $offset = 0) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT id, title, message, type, reference_table, reference_id, is_read, created_at 
                           FROM parent_notifications 
                           WHERE parent_id = ? 
                           ORDER BY created_at DESC 
                           LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $parent_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['time_ago'] = timeAgo($row['created_at']);
        $row['icon'] = getParentNotificationIcon($row['type']);
        $row['color'] = getParentNotificationColor($row['type']);
        $notifications[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $notifications;
}

/**
 * Mark notification as read
 */
function markParentNotificationAsRead($notification_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE parent_notifications SET is_read = TRUE WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

/**
 * Mark all notifications as read
 */
function markAllParentNotificationsAsRead($parent_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE parent_notifications SET is_read = TRUE WHERE parent_id = ?");
    $stmt->bind_param("i", $parent_id);
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

/**
 * Delete notification
 */
function deleteParentNotification($notification_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("DELETE FROM parent_notifications WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $result;
}

/**
 * Get notification icon based on type
 */
function getParentNotificationIcon($type) {
    $icons = [
        'permission_response' => 'fas fa-check-circle',
        'academic_update' => 'fas fa-graduation-cap',
        'attendance_update' => 'fas fa-calendar-check',
        'fee_reminder' => 'fas fa-money-bill-wave',
        'announcement' => 'fas fa-bullhorn',
        'general' => 'fas fa-bell'
    ];
    
    return $icons[$type] ?? 'fas fa-bell';
}

/**
 * Get notification color based on type
 */
function getParentNotificationColor($type) {
    $colors = [
        'permission_response' => '#10b981',
        'academic_update' => '#06b6d4',
        'attendance_update' => '#8b5cf6',
        'fee_reminder' => '#ef4444',
        'announcement' => '#f97316',
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

/**
 * Notification triggers for specific events
 */

// Trigger when permission request is responded to
function notifyPermissionResponse($parent_id, $student_name, $status, $permission_id) {
    $title = "Permission Request " . ucfirst($status);
    $message = "Your permission request for $student_name has been $status";
    return createParentNotification($parent_id, $title, $message, 'permission_response', 'permission_requests', $permission_id);
}

// Trigger when new grades are posted
function notifyAcademicUpdate($parent_id, $student_name, $subject, $grade, $grade_id) {
    $title = "New Grade Posted";
    $message = "Your child $student_name has received $grade in $subject";
    return createParentNotification($parent_id, $title, $message, 'academic_update', 'grades', $grade_id);
}

// Trigger when student marks/grades are added
function notifyNewMarks($student_id, $subject, $marks, $total_marks = 100) {
    try {
        $conn = getDbConnection();

        // Get student and parent information
        $stmt = $conn->prepare("
            SELECT s.first_name, s.last_name, sp.parent_id
            FROM students s
            INNER JOIN student_parent sp ON s.id = sp.student_id
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $student_name = $row['first_name'] . ' ' . $row['last_name'];
            $parent_id = $row['parent_id'];

            $title = "New Marks Added";
            $message = "Your child $student_name has received $marks/$total_marks in $subject";
            createParentNotification($parent_id, $title, $message, 'academic_update', 'marks', $student_id);
        }

        $stmt->close();
        $conn->close();
        return true;
    } catch (Exception $e) {
        error_log("Error creating marks notification: " . $e->getMessage());
        return false;
    }
}

// Trigger when attendance is updated
function notifyAttendanceUpdate($parent_id, $student_name, $attendance_status, $date) {
    $title = "Attendance Update";
    $message = "Your child $student_name was marked $attendance_status on $date";
    return createParentNotification($parent_id, $title, $message, 'attendance_update', 'attendance', null);
}

// Trigger when attendance is collected/recorded
function notifyAttendanceCollected($student_id, $attendance_status, $date, $subject = null) {
    try {
        $conn = getDbConnection();

        // Get student and parent information
        $stmt = $conn->prepare("
            SELECT s.first_name, s.last_name, sp.parent_id
            FROM students s
            INNER JOIN student_parent sp ON s.id = sp.student_id
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $student_name = $row['first_name'] . ' ' . $row['last_name'];
            $parent_id = $row['parent_id'];

            $title = "Attendance Recorded";
            $subject_text = $subject ? " for $subject" : "";
            $message = "Your child $student_name was marked $attendance_status on $date$subject_text";
            createParentNotification($parent_id, $title, $message, 'attendance_update', 'attendance', $student_id);
        }

        $stmt->close();
        $conn->close();
        return true;
    } catch (Exception $e) {
        error_log("Error creating attendance notification: " . $e->getMessage());
        return false;
    }
}

// Trigger when student information is updated
function notifyStudentUpdated($student_id, $update_type = 'profile') {
    try {
        $conn = getDbConnection();

        // Get student and parent information
        $stmt = $conn->prepare("
            SELECT s.first_name, s.last_name, sp.parent_id
            FROM students s
            INNER JOIN student_parent sp ON s.id = sp.student_id
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $student_name = $row['first_name'] . ' ' . $row['last_name'];
            $parent_id = $row['parent_id'];

            $title = "Student Information Updated";
            $message = "Your child $student_name's $update_type information has been updated";
            createParentNotification($parent_id, $title, $message, 'general', 'students', $student_id);
        }

        $stmt->close();
        $conn->close();
        return true;
    } catch (Exception $e) {
        error_log("Error creating student update notification: " . $e->getMessage());
        return false;
    }
}

// Trigger when student is deleted/removed
function notifyStudentDeleted($student_id, $student_name, $parent_id) {
    $title = "Student Record Updated";
    $message = "Important update regarding $student_name's enrollment status. Please contact the school for details.";
    return createParentNotification($parent_id, $title, $message, 'general', 'students', $student_id);
}

// Trigger when fee payment is due
function notifyFeeReminder($parent_id, $student_name, $fee_type, $amount, $due_date) {
    $title = "Fee Payment Due";
    $message = "$fee_type payment of $$amount is due for $student_name by $due_date";
    return createParentNotification($parent_id, $title, $message, 'fee_reminder', 'fees', null);
}

// Trigger when school publishes announcement
function notifyAnnouncement($parent_id, $announcement_title, $announcement_id) {
    $title = "New School Announcement";
    $message = "New announcement: $announcement_title";
    return createParentNotification($parent_id, $title, $message, 'announcement', 'announcements', $announcement_id);
}

// Initialize table on first load
createParentNotificationsTable();
?>
