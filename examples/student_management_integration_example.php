<?php
/**
 * Example: How to integrate notification triggers for student management operations
 * 
 * This file shows how to modify existing student management systems
 * to automatically send notifications to parents when student information is updated or deleted.
 */

require_once '../includes/notification_triggers.php';

/**
 * Example function for updating student information
 */
function updateStudentInfo($student_id, $update_data, $update_type = 'profile') {
    try {
        $conn = getDbConnection();
        
        // Build dynamic update query based on provided data
        $set_clauses = [];
        $params = [];
        $types = '';
        
        foreach ($update_data as $field => $value) {
            $set_clauses[] = "$field = ?";
            $params[] = $value;
            $types .= is_int($value) ? 'i' : 's';
        }
        
        if (empty($set_clauses)) {
            return false;
        }
        
        $sql = "UPDATE students SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $params[] = $student_id;
        $types .= 'i';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            // SUCCESS: Student information updated
            
            // TRIGGER NOTIFICATION: Send notification to parent(s)
            triggerStudentUpdateNotification($student_id, $update_type);
            
            // Log the notification trigger
            $updated_fields = implode(', ', array_keys($update_data));
            logNotificationTrigger('student_updated', $student_id, "Updated: $updated_fields");
            
            $stmt->close();
            $conn->close();
            return true;
        } else {
            $stmt->close();
            $conn->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Error updating student info: " . $e->getMessage());
        return false;
    }
}

/**
 * Example function for deleting/removing a student
 */
function deleteStudent($student_id, $soft_delete = true) {
    try {
        // TRIGGER NOTIFICATION BEFORE DELETION: Send notification to parent(s)
        triggerStudentDeleteNotification($student_id);
        
        $conn = getDbConnection();
        
        if ($soft_delete) {
            // Soft delete - mark as inactive instead of actual deletion
            $stmt = $conn->prepare("UPDATE students SET status = 'inactive', deleted_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $student_id);
        } else {
            // Hard delete - actually remove from database
            $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
            $stmt->bind_param("i", $student_id);
        }
        
        if ($stmt->execute()) {
            // Log the notification trigger
            $delete_type = $soft_delete ? 'soft_delete' : 'hard_delete';
            logNotificationTrigger('student_deleted', $student_id, $delete_type);
            
            $stmt->close();
            $conn->close();
            return true;
        } else {
            $stmt->close();
            $conn->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Error deleting student: " . $e->getMessage());
        return false;
    }
}

/**
 * Example function for updating student class/section
 */
function updateStudentClass($student_id, $new_class_id, $reason = '') {
    try {
        $conn = getDbConnection();
        
        // Get old class info for notification
        $stmt = $conn->prepare("SELECT c.name as old_class FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $old_class = $result->fetch_assoc()['old_class'] ?? 'Unknown';
        $stmt->close();
        
        // Update student class
        $stmt = $conn->prepare("UPDATE students SET class_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $new_class_id, $student_id);
        
        if ($stmt->execute()) {
            // Get new class info
            $stmt2 = $conn->prepare("SELECT name FROM classes WHERE id = ?");
            $stmt2->bind_param("i", $new_class_id);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $new_class = $result2->fetch_assoc()['name'] ?? 'Unknown';
            $stmt2->close();
            
            // TRIGGER NOTIFICATION: Send notification to parent(s)
            triggerStudentUpdateNotification($student_id, "class assignment (moved from $old_class to $new_class)");
            
            // Log the notification trigger
            logNotificationTrigger('student_class_updated', $student_id, "From $old_class to $new_class");
            
            $stmt->close();
            $conn->close();
            return true;
        } else {
            $stmt->close();
            $conn->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Error updating student class: " . $e->getMessage());
        return false;
    }
}

/**
 * Example function for updating student status (active/inactive/suspended)
 */
function updateStudentStatus($student_id, $new_status, $reason = '') {
    try {
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("UPDATE students SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $new_status, $student_id);
        
        if ($stmt->execute()) {
            // TRIGGER NOTIFICATION: Send notification to parent(s)
            $status_message = "status ($new_status)";
            if ($reason) {
                $status_message .= " - $reason";
            }
            triggerStudentUpdateNotification($student_id, $status_message);
            
            // Log the notification trigger
            logNotificationTrigger('student_status_updated', $student_id, "Status: $new_status");
            
            $stmt->close();
            $conn->close();
            return true;
        } else {
            $stmt->close();
            $conn->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Error updating student status: " . $e->getMessage());
        return false;
    }
}

/**
 * Example usage:
 */

// Update student profile information
/*
$update_data = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'phone' => '+1234567890',
    'address' => '123 Main St, City, State'
];

$result = updateStudentInfo(1, $update_data, 'profile');
if ($result) {
    echo "Student profile updated successfully and parent notified!";
}
*/

// Update student class
/*
$result = updateStudentClass(1, 5, 'Promoted to next grade');
if ($result) {
    echo "Student class updated successfully and parent notified!";
}
*/

// Update student status
/*
$result = updateStudentStatus(1, 'suspended', 'Disciplinary action');
if ($result) {
    echo "Student status updated successfully and parent notified!";
}
*/

// Delete student (soft delete)
/*
$result = deleteStudent(1, true);
if ($result) {
    echo "Student removed successfully and parent notified!";
}
*/

/**
 * Integration Instructions:
 * 
 * 1. In your student profile update forms:
 *    - After successfully updating student data
 *    - Add: triggerStudentUpdateNotification($student_id, 'profile');
 * 
 * 2. In your class/section management:
 *    - After changing student's class
 *    - Add: triggerStudentUpdateNotification($student_id, 'class assignment');
 * 
 * 3. In your student status management:
 *    - After changing student status (active/inactive/suspended)
 *    - Add: triggerStudentUpdateNotification($student_id, 'status');
 * 
 * 4. Before deleting students:
 *    - Add: triggerStudentDeleteNotification($student_id);
 * 
 * 5. For bulk operations:
 *    - Loop through students and call appropriate triggers
 */

echo "<h2>Student Management Integration Example</h2>";
echo "<p>This file shows how to integrate parent notifications for student management operations.</p>";
echo "<p>Check the code comments for implementation details.</p>";
?>
