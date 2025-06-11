<?php
// This file contains all modal forms for the school admin dashboard

// Get all departments for dropdown selects
$departments = [];
try {
    $dept_stmt = $conn->prepare("SELECT dep_id, department_name FROM departments WHERE school_id = ? ORDER BY department_name ASC");
    $dept_stmt->bind_param('i', $school_id);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row;
    }
    $dept_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// Get all teachers for class assignment dropdown
$teachers = [];
try {
    $teacher_stmt = $conn->prepare("SELECT id, name FROM teachers WHERE school_id = ? ORDER BY name ASC");
    $teacher_stmt->bind_param('i', $school_id);
    $teacher_stmt->execute();
    $teacher_result = $teacher_stmt->get_result();
    while ($row = $teacher_result->fetch_assoc()) {
        $teachers[] = $row;
    }
    $teacher_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching teachers: " . $e->getMessage());
}

// Get all classes for dropdown selects
$classes = [];
try {
    $class_stmt = $conn->prepare("SELECT DISTINCT class FROM students WHERE school_id = ? ORDER BY class ASC");
    $class_stmt->bind_param('i', $school_id);
    $class_stmt->execute();
    $class_result = $class_stmt->get_result();
    while ($row = $class_result->fetch_assoc()) {
        $classes[] = $row['class'];
    }
    $class_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching classes: " . $e->getMessage());
}
?>

<?php include 'student_multi_step_modal.php'; ?>

