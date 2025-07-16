<?php
/**
 * Notification Trigger System
 * This file contains functions that can be called from anywhere in the system
 * to trigger notifications for parents when certain events occur.
 */

require_once __DIR__ . '/../parent/parent_notifications.php';

/**
 * Trigger notification when student marks/grades are added
 * Call this function whenever marks are added to the system
 */
function triggerMarksNotification($student_id, $subject, $marks, $total_marks = 100) {
    return notifyNewMarks($student_id, $subject, $marks, $total_marks);
}

/**
 * Trigger notification when attendance is collected
 * Call this function whenever attendance is recorded
 */
function triggerAttendanceNotification($student_id, $attendance_status, $date = null, $subject = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    return notifyAttendanceCollected($student_id, $attendance_status, $date, $subject);
}

/**
 * Trigger notification when student information is updated
 * Call this function whenever student profile is updated
 */
function triggerStudentUpdateNotification($student_id, $update_type = 'profile') {
    return notifyStudentUpdated($student_id, $update_type);
}

/**
 * Trigger notification when student is deleted
 * Call this function before deleting a student record
 */
function triggerStudentDeleteNotification($student_id) {
    try {
        $conn = getDbConnection();
        
        // Get student and parent information before deletion
        $stmt = $conn->prepare("
            SELECT s.first_name, s.last_name, sp.parent_id 
            FROM students s 
            INNER JOIN student_parent sp ON s.id = sp.student_id 
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $success = true;
        while ($row = $result->fetch_assoc()) {
            $student_name = $row['first_name'] . ' ' . $row['last_name'];
            $parent_id = $row['parent_id'];
            
            $result = notifyStudentDeleted($student_id, $student_name, $parent_id);
            if (!$result) {
                $success = false;
            }
        }
        
        $stmt->close();
        $conn->close();
        return $success;
    } catch (Exception $e) {
        error_log("Error creating student deletion notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Bulk trigger for multiple students (useful for class-wide operations)
 */
function triggerBulkAttendanceNotification($student_ids, $attendance_status, $date = null, $subject = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $success_count = 0;
    foreach ($student_ids as $student_id) {
        if (triggerAttendanceNotification($student_id, $attendance_status, $date, $subject)) {
            $success_count++;
        }
    }
    
    return $success_count;
}

/**
 * Trigger notification for grade/marks with additional context
 */
function triggerDetailedMarksNotification($student_id, $subject, $marks, $total_marks, $exam_type = 'Test', $teacher_comment = '') {
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
            
            $percentage = round(($marks / $total_marks) * 100, 1);
            $title = "New $exam_type Result";
            $message = "Your child $student_name scored $marks/$total_marks ($percentage%) in $subject";
            
            if ($teacher_comment) {
                $message .= ". Teacher's comment: " . substr($teacher_comment, 0, 100);
                if (strlen($teacher_comment) > 100) {
                    $message .= "...";
                }
            }
            
            createParentNotification($parent_id, $title, $message, 'academic_update', 'marks', $student_id);
        }
        
        $stmt->close();
        $conn->close();
        return true;
    } catch (Exception $e) {
        error_log("Error creating detailed marks notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger notification for attendance with additional context
 */
function triggerDetailedAttendanceNotification($student_id, $attendance_status, $date, $period = null, $subject = null, $notes = '') {
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
            
            $title = "Attendance Update";
            $formatted_date = date('M j, Y', strtotime($date));
            $message = "Your child $student_name was marked $attendance_status on $formatted_date";
            
            if ($period && $subject) {
                $message .= " for $subject (Period $period)";
            } elseif ($subject) {
                $message .= " for $subject";
            } elseif ($period) {
                $message .= " (Period $period)";
            }
            
            if ($notes) {
                $message .= ". Note: " . substr($notes, 0, 50);
                if (strlen($notes) > 50) {
                    $message .= "...";
                }
            }
            
            createParentNotification($parent_id, $title, $message, 'attendance_update', 'attendance', $student_id);
        }
        
        $stmt->close();
        $conn->close();
        return true;
    } catch (Exception $e) {
        error_log("Error creating detailed attendance notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to get student's parents for notification
 */
function getStudentParents($student_id) {
    try {
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("
            SELECT sp.parent_id, p.first_name, p.last_name, p.email 
            FROM student_parent sp 
            INNER JOIN parents p ON sp.parent_id = p.id 
            WHERE sp.student_id = ?
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $parents = [];
        while ($row = $result->fetch_assoc()) {
            $parents[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        return $parents;
    } catch (Exception $e) {
        error_log("Error getting student parents: " . $e->getMessage());
        return [];
    }
}

/**
 * Log notification trigger for debugging
 */
function logNotificationTrigger($event_type, $student_id, $details = '') {
    $log_message = "Notification Trigger: $event_type for student ID $student_id";
    if ($details) {
        $log_message .= " - $details";
    }
    error_log($log_message);
}
?>
