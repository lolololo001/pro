<?php
/**
 * Example: How to integrate notification triggers when recording attendance
 * 
 * This file shows how to modify existing attendance systems
 * to automatically send notifications to parents.
 */

require_once '../includes/notification_triggers.php';

/**
 * Example function for recording student attendance
 */
function recordStudentAttendance($student_id, $attendance_status, $date = null, $period = null, $subject = null, $notes = '') {
    try {
        $conn = getDbConnection();
        
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        // Example: Insert attendance into database
        $stmt = $conn->prepare("INSERT INTO student_attendance (student_id, attendance_status, date, period, subject, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssss", $student_id, $attendance_status, $date, $period, $subject, $notes);
        
        if ($stmt->execute()) {
            // SUCCESS: Attendance recorded in database
            
            // TRIGGER NOTIFICATION: Send notification to parent(s)
            if ($period || $subject || $notes) {
                // Use detailed notification with additional context
                triggerDetailedAttendanceNotification($student_id, $attendance_status, $date, $period, $subject, $notes);
            } else {
                // Use simple notification
                triggerAttendanceNotification($student_id, $attendance_status, $date, $subject);
            }
            
            // Log the notification trigger
            logNotificationTrigger('attendance_recorded', $student_id, "$attendance_status on $date");
            
            $stmt->close();
            $conn->close();
            return true;
        } else {
            $stmt->close();
            $conn->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Error recording attendance: " . $e->getMessage());
        return false;
    }
}

/**
 * Example function for bulk attendance recording (e.g., for entire class)
 */
function recordBulkAttendance($attendance_data) {
    $success_count = 0;
    
    foreach ($attendance_data as $attendance_entry) {
        $student_id = $attendance_entry['student_id'];
        $attendance_status = $attendance_entry['status']; // 'present', 'absent', 'late', 'excused'
        $date = $attendance_entry['date'] ?? date('Y-m-d');
        $period = $attendance_entry['period'] ?? null;
        $subject = $attendance_entry['subject'] ?? null;
        $notes = $attendance_entry['notes'] ?? '';
        
        if (recordStudentAttendance($student_id, $attendance_status, $date, $period, $subject, $notes)) {
            $success_count++;
        }
    }
    
    return $success_count;
}

/**
 * Example function for daily class attendance
 */
function recordClassAttendance($class_id, $subject, $period, $attendance_list, $date = null) {
    try {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $conn = getDbConnection();
        
        // Get all students in the class
        $stmt = $conn->prepare("SELECT id FROM students WHERE class_id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $success_count = 0;
        while ($row = $result->fetch_assoc()) {
            $student_id = $row['id'];
            
            // Check if attendance was marked for this student
            $attendance_status = $attendance_list[$student_id] ?? 'present'; // Default to present if not specified
            
            if (recordStudentAttendance($student_id, $attendance_status, $date, $period, $subject)) {
                $success_count++;
            }
        }
        
        $stmt->close();
        $conn->close();
        return $success_count;
    } catch (Exception $e) {
        error_log("Error recording class attendance: " . $e->getMessage());
        return 0;
    }
}

/**
 * Example usage:
 */

// Single student attendance
/*
$result = recordStudentAttendance(
    student_id: 1,
    attendance_status: 'present',
    date: '2024-01-20',
    period: '1',
    subject: 'Mathematics',
    notes: 'Arrived on time'
);

if ($result) {
    echo "Attendance recorded successfully and parent notified!";
} else {
    echo "Failed to record attendance.";
}
*/

// Bulk attendance example
/*
$bulk_attendance = [
    [
        'student_id' => 1,
        'status' => 'present',
        'date' => '2024-01-20',
        'period' => '1',
        'subject' => 'Mathematics',
        'notes' => ''
    ],
    [
        'student_id' => 2,
        'status' => 'absent',
        'date' => '2024-01-20',
        'period' => '1',
        'subject' => 'Mathematics',
        'notes' => 'Sick leave'
    ],
    [
        'student_id' => 3,
        'status' => 'late',
        'date' => '2024-01-20',
        'period' => '1',
        'subject' => 'Mathematics',
        'notes' => 'Arrived 10 minutes late'
    ]
];

$success_count = recordBulkAttendance($bulk_attendance);
echo "Successfully recorded attendance for $success_count students and sent notifications to parents!";
*/

// Class attendance example
/*
$class_attendance = [
    1 => 'present',
    2 => 'absent',
    3 => 'late',
    4 => 'present',
    5 => 'excused'
];

$success_count = recordClassAttendance(
    class_id: 1,
    subject: 'Mathematics',
    period: '1',
    attendance_list: $class_attendance,
    date: '2024-01-20'
);

echo "Successfully recorded attendance for $success_count students in the class!";
*/

/**
 * Integration Instructions:
 * 
 * 1. In your existing attendance recording system:
 *    - After successfully inserting attendance into database
 *    - Add: triggerAttendanceNotification($student_id, $attendance_status, $date, $subject);
 * 
 * 2. For detailed attendance with period/notes:
 *    - Use: triggerDetailedAttendanceNotification($student_id, $attendance_status, $date, $period, $subject, $notes);
 * 
 * 3. For bulk attendance (entire class):
 *    - Use: triggerBulkAttendanceNotification($student_ids, $attendance_status, $date, $subject);
 * 
 * 4. Common attendance statuses:
 *    - 'present' - Student was present
 *    - 'absent' - Student was absent
 *    - 'late' - Student arrived late
 *    - 'excused' - Student had excused absence
 *    - 'tardy' - Student was tardy
 */

echo "<h2>Attendance Integration Example</h2>";
echo "<p>This file shows how to integrate parent notifications when recording attendance.</p>";
echo "<p>Check the code comments for implementation details.</p>";
?>
