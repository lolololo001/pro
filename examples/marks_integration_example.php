<?php
/**
 * Example: How to integrate notification triggers when adding marks/grades
 * 
 * This file shows how to modify existing grade/marks entry systems
 * to automatically send notifications to parents.
 */

require_once '../includes/notification_triggers.php';

/**
 * Example function for adding student marks
 * This would typically be in your marks/grades management system
 */
function addStudentMarks($student_id, $subject, $marks, $total_marks = 100, $exam_type = 'Test', $teacher_comment = '') {
    try {
        $conn = getDbConnection();
        
        // Example: Insert marks into database
        $stmt = $conn->prepare("INSERT INTO student_marks (student_id, subject, marks, total_marks, exam_type, teacher_comment, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isiiis", $student_id, $subject, $marks, $total_marks, $exam_type, $teacher_comment);
        
        if ($stmt->execute()) {
            // SUCCESS: Marks added to database
            
            // TRIGGER NOTIFICATION: Send notification to parent(s)
            if ($teacher_comment) {
                // Use detailed notification with teacher comment
                triggerDetailedMarksNotification($student_id, $subject, $marks, $total_marks, $exam_type, $teacher_comment);
            } else {
                // Use simple notification
                triggerMarksNotification($student_id, $subject, $marks, $total_marks);
            }
            
            // Log the notification trigger
            logNotificationTrigger('marks_added', $student_id, "$subject: $marks/$total_marks");
            
            $stmt->close();
            $conn->close();
            return true;
        } else {
            $stmt->close();
            $conn->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Error adding student marks: " . $e->getMessage());
        return false;
    }
}

/**
 * Example function for bulk marks entry (e.g., for entire class)
 */
function addBulkMarks($marks_data) {
    $success_count = 0;
    
    foreach ($marks_data as $mark_entry) {
        $student_id = $mark_entry['student_id'];
        $subject = $mark_entry['subject'];
        $marks = $mark_entry['marks'];
        $total_marks = $mark_entry['total_marks'] ?? 100;
        $exam_type = $mark_entry['exam_type'] ?? 'Test';
        $teacher_comment = $mark_entry['teacher_comment'] ?? '';
        
        if (addStudentMarks($student_id, $subject, $marks, $total_marks, $exam_type, $teacher_comment)) {
            $success_count++;
        }
    }
    
    return $success_count;
}

/**
 * Example usage:
 */

// Single student marks entry
/*
$result = addStudentMarks(
    student_id: 1,
    subject: 'Mathematics',
    marks: 85,
    total_marks: 100,
    exam_type: 'Mid-term Exam',
    teacher_comment: 'Excellent work! Shows good understanding of algebra concepts.'
);

if ($result) {
    echo "Marks added successfully and parent notified!";
} else {
    echo "Failed to add marks.";
}
*/

// Bulk marks entry example
/*
$bulk_marks = [
    [
        'student_id' => 1,
        'subject' => 'Mathematics',
        'marks' => 85,
        'total_marks' => 100,
        'exam_type' => 'Quiz',
        'teacher_comment' => 'Good work!'
    ],
    [
        'student_id' => 2,
        'subject' => 'Mathematics',
        'marks' => 92,
        'total_marks' => 100,
        'exam_type' => 'Quiz',
        'teacher_comment' => 'Excellent!'
    ],
    [
        'student_id' => 3,
        'subject' => 'Mathematics',
        'marks' => 78,
        'total_marks' => 100,
        'exam_type' => 'Quiz',
        'teacher_comment' => 'Needs improvement in problem solving.'
    ]
];

$success_count = addBulkMarks($bulk_marks);
echo "Successfully added marks for $success_count students and sent notifications to parents!";
*/

/**
 * Integration Instructions:
 * 
 * 1. In your existing marks/grades entry form processing:
 *    - After successfully inserting marks into database
 *    - Add: triggerMarksNotification($student_id, $subject, $marks, $total_marks);
 * 
 * 2. In your grade book or marks management system:
 *    - After any grade update/modification
 *    - Add: triggerMarksNotification($student_id, $subject, $marks, $total_marks);
 * 
 * 3. For detailed notifications with teacher comments:
 *    - Use: triggerDetailedMarksNotification($student_id, $subject, $marks, $total_marks, $exam_type, $teacher_comment);
 * 
 * 4. For bulk operations (entire class):
 *    - Loop through students and call trigger for each
 *    - Or modify the trigger functions to accept arrays
 */

echo "<h2>Marks Integration Example</h2>";
echo "<p>This file shows how to integrate parent notifications when adding student marks.</p>";
echo "<p>Check the code comments for implementation details.</p>";
?>
