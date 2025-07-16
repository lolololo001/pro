<?php
// Start session
session_start();

// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once '../config/config.php';
require_once '../includes/notification_triggers.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get teacher_id and school_id from session
$teacher_id = $_SESSION['teacher_id'] ?? 0;
$school_id = $_SESSION['teacher_school_id'] ?? 0;

// Debug: Log session data
error_log("Session data - Teacher ID: $teacher_id, School ID: $school_id");

if (!$teacher_id || !$school_id) {
    die("Error: Teacher session not found. Please log in again.");
}

// Get database connection
$conn = getDbConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['take_attendance'])) {
        // Debug: Log form submission
        error_log("Form submitted: " . print_r($_POST, true));
        
        $class_id = intval($_POST['class_id']);
        $date = $_POST['date'];
        $attendance_data = $_POST['attendance'] ?? [];
        
        // Debug: Log processed data
        error_log("Processed data - Class ID: $class_id, Date: $date, Attendance count: " . count($attendance_data));
        
        try {
            $subject = $_POST['subject'] ?? '';

            // Validate input data
            if (empty($attendance_data)) {
                throw new Exception("No attendance data received");
            }

            if (empty($class_id) || empty($date)) {
                throw new Exception("Missing required data: class_id or date");
            }

            $processed_count = 0;
            $error_count = 0;
            $notification_count = 0;
            $attendance_summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];

            // Process each student's attendance
            foreach ($attendance_data as $student_id => $status) {
                // Check if attendance already exists
                $check_query = "SELECT id FROM student_attendance WHERE class_id = ? AND student_id = ? AND date = ?";
                $check_params = [$class_id, $student_id, $date];
                $check_types = 'iis';

                if (!empty($subject)) {
                    $check_query .= " AND subject = ?";
                    $check_params[] = $subject;
                    $check_types .= 's';
                }

                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param($check_types, ...$check_params);
                $check_stmt->execute();
                $result = $check_stmt->get_result();

                if ($result->num_rows > 0) {
                    // Update existing attendance
                    $update_query = "UPDATE student_attendance SET status = ?, updated_at = NOW() WHERE class_id = ? AND student_id = ? AND date = ?";
                    $update_params = [$status, $class_id, $student_id, $date];
                    $update_types = 'siis';

                    if (!empty($subject)) {
                        $update_query .= " AND subject = ?";
                        $update_params[] = $subject;
                        $update_types .= 's';
                    }

                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param($update_types, ...$update_params);

                    if ($update_stmt->execute()) {
                        $processed_count++;
                        $attendance_summary[$status]++;

                        // Send notification to parent
                        if (triggerDetailedAttendanceNotification($student_id, $status, $date, null, $subject, '')) {
                            $notification_count++;
                        }
                    } else {
                        $error_count++;
                        error_log("Failed to update attendance for student $student_id: " . $conn->error);
                    }
                    $update_stmt->close();
                } else {
                    // Insert new attendance record
                    $insert_query = "INSERT INTO student_attendance (class_id, student_id, date, status, teacher_id, subject, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                    $insert_params = [$class_id, $student_id, $date, $status, $teacher_id, $subject];
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->bind_param('iissis', ...$insert_params);

                    if ($insert_stmt->execute()) {
                        $processed_count++;
                        $attendance_summary[$status]++;

                        // Send notification to parent
                        if (triggerDetailedAttendanceNotification($student_id, $status, $date, null, $subject, '')) {
                            $notification_count++;
                        }
                    } else {
                        $error_count++;
                        error_log("Failed to insert attendance for student $student_id: " . $conn->error);
                    }
                    $insert_stmt->close();
                }

                $check_stmt->close();
            }

            // Enhanced success message with detailed attendance report
            $subject_text = !empty($subject) ? " for $subject" : "";
            $report = "Present: {$attendance_summary['present']}, Absent: {$attendance_summary['absent']}, Late: {$attendance_summary['late']}, Excused: {$attendance_summary['excused']}";

            if ($error_count > 0) {
                $_SESSION['teacher_success'] = "Attendance partially saved$subject_text! Report: $report. $processed_count records saved, $error_count errors. $notification_count parent notifications sent.";
            } else {
                $_SESSION['teacher_success'] = "Attendance saved successfully$subject_text! Report: $report. All $processed_count records stored in database. $notification_count parent notifications sent.";
            }

            $_SESSION['show_popup'] = true;
            $_SESSION['attendance_saved'] = true; // Flag to show confirmation popup
            $_SESSION['processed_count'] = $processed_count; // Store processed count for popup
            
            // Log successful attendance submission
            error_log("Attendance saved successfully: Class $class_id, Date $date, Subject: $subject, Students: " . count($attendance_data) . ", Processed: $processed_count, Errors: $error_count, Notifications: $notification_count");

        } catch (Exception $e) {
            $_SESSION['teacher_error'] = 'Error saving attendance: ' . $e->getMessage();
            error_log("Attendance submission error: " . $e->getMessage());
        }
        
        // Stay on the same page with current parameters
        $redirect_url = 'attendance.php?class_id=' . $class_id . '&date=' . $date;
        if (!empty($subject)) {
            $redirect_url .= '&subject=' . urlencode($subject);
        }
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Create student_attendance table if it doesn't exist
try {
    // First, check if table exists and if subject column exists
    $table_check = $conn->query("SHOW TABLES LIKE 'student_attendance'");

    if ($table_check->num_rows > 0) {
        // Table exists, check if subject column exists
        $column_check = $conn->query("SHOW COLUMNS FROM student_attendance LIKE 'subject'");

        if ($column_check->num_rows == 0) {
            // Add subject column if it doesn't exist
            $conn->query("ALTER TABLE student_attendance ADD COLUMN subject VARCHAR(100) AFTER date");
            error_log("Added subject column to student_attendance table");
        }
    } else {
        // Create new table
        $conn->query("CREATE TABLE student_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            student_id INT NOT NULL,
            date DATE NOT NULL,
            subject VARCHAR(100),
            status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
            teacher_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_class_date (class_id, date),
            INDEX idx_student_date (student_id, date),
            INDEX idx_teacher (teacher_id)
        )");
        error_log("Created student_attendance table");
    }
} catch (Exception $e) {
    error_log("Error with student_attendance table: " . $e->getMessage());
}

// Get teacher information
try {
    $stmt = $conn->prepare('SELECT t.*, d.department_name 
                           FROM teachers t 
                           LEFT JOIN departments d ON t.department_id = d.dep_id 
                           WHERE t.id = ? AND t.school_id = ?');
    $stmt->bind_param('ii', $teacher_id, $school_id);
    $stmt->execute();
    $teacher_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching teacher info: " . $e->getMessage());
}

// Get filter parameters
$class_filter = $_GET['class_id'] ?? '';
$subject_filter = $_GET['subject'] ?? '';
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Get assigned classes
$assigned_classes = [];
try {
    $stmt = $conn->prepare('SELECT c.id, c.class_name, c.grade_level 
                           FROM classes c 
                           WHERE c.teacher_id = ? AND c.school_id = ?
                           ORDER BY c.grade_level ASC, c.class_name ASC');
    $stmt->bind_param('ii', $teacher_id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $assigned_classes[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching assigned classes: " . $e->getMessage());
}

// Get subjects for the selected class
$class_subjects = [];
if (!empty($class_filter)) {
    try {
        // First try to get subjects from modules table
        $stmt = $conn->prepare('SELECT DISTINCT m.module_id, m.module_name as module_name, m.module_code
                               FROM modules m
                               WHERE m.school_id = ? AND m.status = "active"
                               ORDER BY m.module_name ASC');
        $stmt->bind_param('i', $school_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $class_subjects[] = $row;
        }
        $stmt->close();

        // If no modules found, add common subjects
        if (empty($class_subjects)) {
            $common_subjects = [
                ['module_name' => 'Mathematics', 'module_code' => 'MATH'],
                ['module_name' => 'English', 'module_code' => 'ENG'],
                ['module_name' => 'Science', 'module_code' => 'SCI'],
                ['module_name' => 'Social Studies', 'module_code' => 'SS'],
                ['module_name' => 'Physical Education', 'module_code' => 'PE'],
                ['module_name' => 'Art', 'module_code' => 'ART'],
                ['module_name' => 'Music', 'module_code' => 'MUS'],
                ['module_name' => 'Computer Science', 'module_code' => 'CS']
            ];
            $class_subjects = $common_subjects;
        }
    } catch (Exception $e) {
        error_log("Error fetching class subjects: " . $e->getMessage());
        // Fallback to common subjects
        $common_subjects = [
            ['module_name' => 'Mathematics', 'module_code' => 'MATH'],
            ['module_name' => 'English', 'module_code' => 'ENG'],
            ['module_name' => 'Science', 'module_code' => 'SCI'],
            ['module_name' => 'Social Studies', 'module_code' => 'SS'],
            ['module_name' => 'Physical Education', 'module_code' => 'PE']
        ];
        $class_subjects = $common_subjects;
    }
}

// Get students and their attendance for selected class and date
$students = [];
$existing_attendance = [];
if (!empty($class_filter)) {
    try {
        // Get students
        $stmt = $conn->prepare('SELECT s.*, c.class_name, c.grade_level 
                               FROM students s 
                               LEFT JOIN classes c ON s.class_id = c.id
                               WHERE s.class_id = ? AND s.school_id = ?
                               ORDER BY s.first_name ASC, s.last_name ASC');
        $stmt->bind_param('ii', $class_filter, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
        
        // Get existing attendance for the date and subject (if specified)
        $attendance_query = 'SELECT student_id, status FROM student_attendance WHERE class_id = ? AND date = ?';
        $attendance_params = [$class_filter, $date_filter];
        $attendance_types = 'is';
        
        if (!empty($subject_filter)) {
            $attendance_query .= ' AND subject = ?';
            $attendance_params[] = $subject_filter;
            $attendance_types .= 's';
        }
        
        $stmt = $conn->prepare($attendance_query);
        $stmt->bind_param($attendance_types, ...$attendance_params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $existing_attendance[$row['student_id']] = $row['status'];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching students/attendance: " . $e->getMessage());
    }
}



// Get school info
$school_info = [];
try {
    $stmt = $conn->prepare('SELECT name, logo, address, phone, email FROM schools WHERE id = ?');
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $school_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching school info: " . $e->getMessage());
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - Teacher Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #00704a;
            --accent-color: #4caf50;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #ffffff;
            --dark-color: #333333;
            --border-color: #e9ecef;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --sidebar-width: 280px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: var(--dark-color);
            line-height: 1.6;
        }

        /* Header Styles */
        .header {
            background: var(--light-color);
            height: var(--header-height);
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: var(--shadow-sm);
            border-bottom: 1px solid var(--border-color);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--light-color);
        }

        .btn-primary:hover {
            background: #005a3c;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--info-color);
            color: var(--light-color);
        }

        .btn-secondary:hover {
            background: #138496;
        }

        .btn-success {
            background: var(--accent-color);
            color: var(--light-color);
        }

        .btn-success:hover {
            background: #45a049;
        }

        .btn-warning {
            background: var(--warning-color);
            color: var(--dark-color);
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: var(--danger-color);
            color: var(--light-color);
        }

        .btn-danger:hover {
            background: #c82333;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            color: var(--light-color);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--light-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .school-logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .school-logo, .school-logo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .school-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .school-logo-placeholder i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .sidebar-user {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.8rem;
            color: white;
            font-weight: bold;
        }

        .user-info h3 {
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }

        .user-info p {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-heading {
            padding: 0.5rem 1.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
        }

        .menu-item {
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .menu-item:hover, .menu-item.active {
            background-color: var(--accent-color);
        }

        .menu-item i {
            margin-right: 0.8rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .menu-item a {
            color: var(--light-color);
            text-decoration: none;
            font-weight: 500;
            flex: 1;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: #666;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb span {
            margin: 0 0.5rem;
        }

        /* Filter Section */
        .filter-section {
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        .form-control {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 112, 74, 0.15);
        }



        /* Card Styles */
        .card {
            background: var(--light-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: #f8f9fa;
        }

        .card-header h3 {
            margin: 0;
            color: var(--dark-color);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Attendance Form */
        .attendance-form {
            display: grid;
            gap: 1rem;
        }

        .student-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: #f8f9fa;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .student-details h4 {
            margin: 0;
            font-size: 1rem;
            color: var(--dark-color);
        }

        .student-details p {
            margin: 0;
            font-size: 0.8rem;
            color: #666;
        }

        .attendance-options {
            display: flex;
            gap: 0.5rem;
        }

        .attendance-option {
            display: none;
        }

        .attendance-option + label {
            padding: 0.5rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .attendance-option:checked + label {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }

        .attendance-option[value="present"] + label {
            border-color: var(--accent-color);
            color: var(--accent-color);
        }

        .attendance-option[value="present"]:checked + label {
            background: var(--accent-color);
            color: white;
        }

        .attendance-option[value="absent"] + label {
            border-color: var(--danger-color);
            color: var(--danger-color);
        }

        .attendance-option[value="absent"]:checked + label {
            background: var(--danger-color);
            color: white;
        }

        .attendance-option[value="late"] + label {
            border-color: var(--warning-color);
            color: var(--warning-color);
        }

        .attendance-option[value="late"]:checked + label {
            background: var(--warning-color);
            color: white;
        }

        .attendance-option[value="excused"] + label {
            border-color: var(--info-color);
            color: var(--info-color);
        }

        .attendance-option[value="excused"]:checked + label {
            background: var(--info-color);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }

        .empty-icon {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        .empty-text {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .header {
                left: 0;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .student-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .attendance-options {
                justify-content: center;
            }
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            position: relative;
            animation: slideInDown 0.3s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-content {
            flex: 1;
        }

        .alert-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .alert-close:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Success Popup Modal */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }

        .popup-modal {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: popupSlideIn 0.3s ease-out;
        }

        @keyframes popupSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .popup-icon {
            font-size: 4rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .popup-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .popup-message {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .popup-details {
            text-align: left;
        }

        .popup-details p {
            margin-bottom: 1rem;
        }

        .popup-details code {
            background: #f8f9fa;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: monospace;
            color: #e83e8c;
        }

        .storage-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        .info-item i {
            color: var(--primary-color);
            width: 16px;
        }

        .popup-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .popup-btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .popup-btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .popup-btn-primary:hover {
            background: #005a3c;
            transform: translateY(-1px);
        }

        .popup-btn-secondary {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #dee2e6;
        }

        .popup-btn-secondary:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <h1 class="header-title">Attendance Management</h1>
        </div>
        <div class="header-actions">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> Student Attendance</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Attendance</span>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['teacher_success'])): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i> 
                <div class="alert-content">
                    <strong>✅ Attendance Saved Successfully!</strong><br>
                    <?php
                    echo $_SESSION['teacher_success'];
                    unset($_SESSION['teacher_success']);
                    ?>
                </div>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['teacher_error'])): ?>
            <div class="alert alert-danger" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i> 
                <div class="alert-content">
                    <strong>❌ Error Occurred!</strong><br>
                    <?php
                    echo $_SESSION['teacher_error'];
                    unset($_SESSION['teacher_error']);
                    ?>
                </div>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['attendance_saved']) && $_SESSION['attendance_saved']): ?>
            <div class="alert alert-success" id="confirmationAlert" style="background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb;">
                <i class="fas fa-info-circle"></i> 
                <div class="alert-content">
                    <strong>📊 Attendance Confirmation</strong><br>
                    Your attendance data has been successfully saved to the database. You can continue taking attendance for other classes or dates.
                </div>
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['attendance_saved']); ?>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="attendance.php" class="filter-form">
                <div class="form-group">
                    <label for="class_filter">Select Class</label>
                    <select name="class_id" id="class_filter" class="form-control" onchange="this.form.submit()">
                        <option value="">Choose Class</option>
                        <?php foreach ($assigned_classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                Grade <?php echo htmlspecialchars($class['grade_level']); ?> - 
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_filter">Select Date</label>
                    <input type="date" name="date" id="date_filter" class="form-control" 
                           value="<?php echo htmlspecialchars($date_filter); ?>" onchange="this.form.submit()">
                </div>
                
                <?php if (!empty($class_subjects)): ?>
                    <div class="form-group">
                        <label for="subject_filter">Select Subject</label>
                        <select name="subject" id="subject_filter" class="form-control" onchange="this.form.submit()">
                            <option value="">All Subjects</option>
                            <?php foreach ($class_subjects as $subject): ?>
                                <option value="<?php echo htmlspecialchars($subject['module_name']); ?>" 
                                        <?php echo $subject_filter == $subject['module_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['module_name']); ?>
                                    <?php if (!empty($subject['module_code'])): ?>
                                        (<?php echo htmlspecialchars($subject['module_code']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </form>
        </div>



        <!-- Take Attendance -->
        <?php if (!empty($students)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-edit"></i> Take Attendance for <?php echo date('l, F d, Y', strtotime($date_filter)); ?>
                        <?php if (!empty($subject_filter)): ?>
                            - <?php echo htmlspecialchars($subject_filter); ?>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="attendance.php" class="attendance-form">
                        <input type="hidden" name="class_id" value="<?php echo $class_filter; ?>">
                        <input type="hidden" name="date" value="<?php echo $date_filter; ?>">
                        <?php if (!empty($subject_filter)): ?>
                            <input type="hidden" name="subject" value="<?php echo htmlspecialchars($subject_filter); ?>">
                        <?php endif; ?>
                        
                        <?php foreach ($students as $student): ?>
                            <div class="student-row">
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                    </div>
                                    <div class="student-details">
                                        <h4><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($student['admission_number']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="attendance-options">
                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                           value="present" id="present_<?php echo $student['id']; ?>" 
                                           class="attendance-option"
                                           <?php echo (isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']] == 'present') ? 'checked' : ''; ?>>
                                    <label for="present_<?php echo $student['id']; ?>">Present</label>
                                    
                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                           value="absent" id="absent_<?php echo $student['id']; ?>" 
                                           class="attendance-option"
                                           <?php echo (isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']] == 'absent') ? 'checked' : ''; ?>>
                                    <label for="absent_<?php echo $student['id']; ?>">Absent</label>
                                    
                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                           value="late" id="late_<?php echo $student['id']; ?>" 
                                           class="attendance-option"
                                           <?php echo (isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']] == 'late') ? 'checked' : ''; ?>>
                                    <label for="late_<?php echo $student['id']; ?>">Late</label>
                                    
                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" 
                                           value="excused" id="excused_<?php echo $student['id']; ?>" 
                                           class="attendance-option"
                                           <?php echo (isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']] == 'excused') ? 'checked' : ''; ?>>
                                    <label for="excused_<?php echo $student['id']; ?>">Excused</label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div style="margin-top: 2rem; text-align: right;">
                            <button type="submit" name="take_attendance" class="btn btn-primary" id="saveAttendanceBtn">
                                <i class="fas fa-save"></i> Save Attendance
                            </button>
                            <div class="save-status" id="saveStatus" style="display: none; margin-top: 0.5rem; font-size: 0.9rem; color: #666;">
                                <i class="fas fa-info-circle"></i> Click "Save Attendance" to record the attendance data
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif (!empty($class_filter)): ?>
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-user-graduate"></i></div>
                        <div class="empty-text">No Students Found</div>
                        <p>No students are currently enrolled in this class.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($class_filter)): ?>
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-school"></i></div>
                        <div class="empty-text">Select a Class</div>
                        <p>Please select a class from the dropdown above to start taking attendance.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Success Popup Modal -->
    <div id="successPopup" class="popup-overlay">
        <div class="popup-modal">
            <div class="popup-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="popup-title">Attendance Stored Successfully!</div>
            <div class="popup-message" id="popupMessage">
                <div class="popup-details">
                    <p><strong>✅ Database Storage Confirmed</strong></p>
                    <p>Your attendance data has been successfully stored in the <code>student_attendance</code> table.</p>
                    <div class="storage-info">
                        <div class="info-item">
                            <i class="fas fa-database"></i>
                            <span>Records stored in database</span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-calendar"></i>
                            <span>Date: <span id="attendanceDate"></span></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-book"></i>
                            <span>Subject: <span id="attendanceSubject"></span></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="popup-actions">
                <button class="popup-btn popup-btn-primary" onclick="closeSuccessPopup()">
                    <i class="fas fa-check"></i> Continue
                </button>
                <button class="popup-btn popup-btn-secondary" onclick="takeMoreAttendance()">
                    <i class="fas fa-calendar-check"></i> Take More Attendance
                </button>
            </div>
        </div>
    </div>

    <script>
        // Enhanced JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alerts after 8 seconds (increased for better readability)
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                });
            }, 8000);

            // Set default attendance to present if none selected
            const studentRows = document.querySelectorAll('.student-row');
            studentRows.forEach(function(row) {
                const options = row.querySelectorAll('.attendance-option');
                const hasChecked = Array.from(options).some(option => option.checked);
                
                if (!hasChecked) {
                    const presentOption = row.querySelector('input[value="present"]');
                    if (presentOption) {
                        presentOption.checked = true;
                    }
                }
            });

            // Enhanced form submission handling
            const attendanceForm = document.querySelector('.attendance-form');
            if (attendanceForm) {
                attendanceForm.addEventListener('submit', function(e) {
                    const submitButton = this.querySelector('button[type="submit"]');
                    const originalText = submitButton.innerHTML;
                    
                    // Show loading state
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    submitButton.disabled = true;
                    
                    // Re-enable button after 3 seconds if form doesn't submit
                    setTimeout(function() {
                        if (submitButton.disabled) {
                            submitButton.innerHTML = originalText;
                            submitButton.disabled = false;
                        }
                    }, 3000);
                });
            }

            // Enhanced alert handling
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                // Add click to dismiss functionality
                alert.addEventListener('click', function(e) {
                    if (e.target.classList.contains('alert-close') || e.target.closest('.alert-close')) {
                        this.style.opacity = '0';
                        setTimeout(() => this.remove(), 300);
                    }
                });

                // Add keyboard support for dismissal
                alert.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        this.style.opacity = '0';
                        setTimeout(() => this.remove(), 300);
                    }
                });
            });

            // Scroll to top when showing success message
            <?php if (isset($_SESSION['teacher_success']) || isset($_SESSION['attendance_saved'])): ?>
                window.scrollTo({ top: 0, behavior: 'smooth' });
            <?php endif; ?>
        });

        // Success popup functions
        function showSuccessPopup(message, date, subject, recordCount) {
            // Update the popup message
            const popupMessage = document.getElementById('popupMessage');
            const messageDiv = popupMessage.querySelector('.popup-details');
            
            if (messageDiv) {
                // Update the storage info
                const dateSpan = document.getElementById('attendanceDate');
                const subjectSpan = document.getElementById('attendanceSubject');
                
                if (dateSpan) dateSpan.textContent = date || 'Today';
                if (subjectSpan) subjectSpan.textContent = subject || 'All Subjects';
                
                // Update record count
                const recordInfo = popupMessage.querySelector('.info-item span');
                if (recordInfo) {
                    recordInfo.textContent = `${recordCount} attendance records stored in database`;
                }
            }
            
            document.getElementById('successPopup').style.display = 'flex';
        }

        function closeSuccessPopup() {
            document.getElementById('successPopup').style.display = 'none';
        }

        function takeMoreAttendance() {
            closeSuccessPopup();
            // Optionally redirect to a new date or class
            window.location.href = 'attendance.php';
        }

        // Show popup if attendance was just saved
        <?php if (isset($_SESSION['show_popup']) && $_SESSION['show_popup']): ?>
            <?php if (isset($_SESSION['teacher_success'])): ?>
                showSuccessPopup(
                    '<?php echo addslashes($_SESSION['teacher_success']); ?>',
                    '<?php echo isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); ?>',
                    '<?php echo isset($_GET['subject']) ? addslashes($_GET['subject']) : 'All Subjects'; ?>',
                    '<?php echo isset($_SESSION['processed_count']) ? $_SESSION['processed_count'] : '0'; ?>'
                );
                <?php 
                unset($_SESSION['teacher_success'], $_SESSION['show_popup']); 
                if (isset($_SESSION['processed_count'])) unset($_SESSION['processed_count']);
                ?>
            <?php endif; ?>
        <?php endif; ?>

        // Close popup when clicking outside
        document.getElementById('successPopup').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSuccessPopup();
            }
        });

        // Close popup with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSuccessPopup();
            }
        });
    </script>
</body>
</html> 