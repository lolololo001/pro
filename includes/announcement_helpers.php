<?php
/**
 * Helper functions for announcement tracking
 */

/**
 * Check if parent has new announcements
 */
function hasNewAnnouncements($parent_id) {
    $conn = getDbConnection();
    
    try {
        // Get parent's school_id
        $stmt = $conn->prepare("SELECT s.school_id FROM students s INNER JOIN student_parent sp ON s.id = sp.student_id WHERE sp.parent_id = ? LIMIT 1");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            return false;
        }
        
        $school_id = $row["school_id"];
        $stmt->close();
        
        // Get parent's last login time
        $stmt = $conn->prepare("SELECT last_login FROM parents WHERE id = ?");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $parent_data = $result->fetch_assoc();
        $last_login = $parent_data["last_login"] ?? "1970-01-01 00:00:00";
        $stmt->close();
        
        // Check for announcements published after last login
        $today = date("Y-m-d");
        $stmt = $conn->prepare("
            SELECT COUNT(*) as new_count 
            FROM announcements a
            LEFT JOIN parent_announcement_views pav ON a.id = pav.announcement_id AND pav.parent_id = ?
            WHERE a.school_id = ? 
            AND (a.expiry_date IS NULL OR a.expiry_date >= ?) 
            AND (a.target_group = \"all\" OR a.target_group = \"parents\")
            AND a.created_at > ?
            AND pav.id IS NULL
        ");
        $stmt->bind_param("iiss", $parent_id, $school_id, $today, $last_login);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()["new_count"];
        $stmt->close();
        
        return $count > 0;
        
    } catch (Exception $e) {
        error_log("Error checking new announcements: " . $e->getMessage());
        return false;
    } finally {
        $conn->close();
    }
}

/**
 * Mark announcements as viewed by parent
 */
function markAnnouncementsAsViewed($parent_id) {
    $conn = getDbConnection();
    
    try {
        // Get parent's school_id
        $stmt = $conn->prepare("SELECT s.school_id FROM students s INNER JOIN student_parent sp ON s.id = sp.student_id WHERE sp.parent_id = ? LIMIT 1");
        $stmt->bind_param("i", $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            return false;
        }
        
        $school_id = $row["school_id"];
        $stmt->close();
        
        // Get all current announcements for this parent
        $today = date("Y-m-d");
        $stmt = $conn->prepare("
            SELECT a.id 
            FROM announcements a
            WHERE a.school_id = ? 
            AND (a.expiry_date IS NULL OR a.expiry_date >= ?) 
            AND (a.target_group = \"all\" OR a.target_group = \"parents\")
        ");
        $stmt->bind_param("is", $school_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Mark each announcement as viewed
        while ($row = $result->fetch_assoc()) {
            $view_stmt = $conn->prepare("INSERT IGNORE INTO parent_announcement_views (parent_id, announcement_id) VALUES (?, ?)");
            $view_stmt->bind_param("ii", $parent_id, $row["id"]);
            $view_stmt->execute();
            $view_stmt->close();
        }
        $stmt->close();
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error marking announcements as viewed: " . $e->getMessage());
        return false;
    } finally {
        $conn->close();
    }
}

/**
 * Update parent last login time
 */
function updateParentLastLogin($parent_id) {
    $conn = getDbConnection();
    
    try {
        $stmt = $conn->prepare("UPDATE parents SET last_login = NOW() WHERE id = ?");
        $stmt->bind_param("i", $parent_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error updating parent last login: " . $e->getMessage());
        return false;
    } finally {
        $conn->close();
    }
}
?>