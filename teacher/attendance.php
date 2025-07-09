<?php
// Start session
session_start();

// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once '../config/config.php';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get teacher_id and school_id from session
$teacher_id = $_SESSION['teacher_id'] ?? 0;
$school_id = $_SESSION['teacher_school_id'] ?? 0;

if (!$teacher_id || !$school_id) {
    die("Error: Teacher session not found. Please log in again.");
}

// Get database connection
$conn = getDbConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['take_attendance'])) {
        $class_id = intval($_POST['class_id']);
        $date = $_POST['date'];
        $attendance_data = $_POST['attendance'] ?? [];
        
        try {
            // Check if attendance already exists for this class and date
            $check_stmt = $conn->prepare("SELECT id FROM student_attendance WHERE class_id = ? AND date = ?");
            $check_stmt->bind_param('is', $class_id, $date);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing attendance
                foreach ($attendance_data as $student_id => $status) {
                    $update_stmt = $conn->prepare("UPDATE student_attendance SET status = ?, updated_at = NOW() WHERE class_id = ? AND student_id = ? AND date = ?");
                    $update_stmt->bind_param('siis', $status, $class_id, $student_id, $date);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                $_SESSION['teacher_success'] = 'Attendance updated successfully!';
            } else {
                // Insert new attendance records
                foreach ($attendance_data as $student_id => $status) {
                    $insert_stmt = $conn->prepare("INSERT INTO student_attendance (class_id, student_id, date, status, teacher_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $insert_stmt->bind_param('iissi', $class_id, $student_id, $date, $status, $teacher_id);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
                $_SESSION['teacher_success'] = 'Attendance recorded successfully!';
            }
            $check_stmt->close();
        } catch (Exception $e) {
            $_SESSION['teacher_error'] = 'System error: ' . $e->getMessage();
        }
        
        header('Location: attendance.php?class_id=' . $class_id . '&date=' . $date);
        exit;
    }
}

// Create student_attendance table if it doesn't exist
try {
    $conn->query("CREATE TABLE IF NOT EXISTS student_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        student_id INT NOT NULL,
        date DATE NOT NULL,
        status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
        teacher_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
        UNIQUE KEY unique_attendance (class_id, student_id, date)
    )");
} catch (Exception $e) {
    error_log("Error creating student_attendance table: " . $e->getMessage());
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
        
        // Get existing attendance for the date
        $stmt = $conn->prepare('SELECT student_id, status FROM student_attendance 
                               WHERE class_id = ? AND date = ?');
        $stmt->bind_param('is', $class_filter, $date_filter);
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

// Get attendance statistics
$attendance_stats = [];
if (!empty($class_filter)) {
    try {
        $stmt = $conn->prepare('SELECT 
                                   COUNT(*) as total_students,
                                   SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present,
                                   SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent,
                                   SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late,
                                   SUM(CASE WHEN status = "excused" THEN 1 ELSE 0 END) as excused
                               FROM student_attendance 
                               WHERE class_id = ? AND date = ?');
        $stmt->bind_param('is', $class_filter, $date_filter);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance_stats = $result->fetch_assoc();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching attendance stats: " . $e->getMessage());
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--light-color);
            padding: 1rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        .stat-present .stat-number { color: var(--accent-color); }
        .stat-absent .stat-number { color: var(--danger-color); }
        .stat-late .stat-number { color: var(--warning-color); }
        .stat-excused .stat-number { color: var(--info-color); }

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
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php
                echo $_SESSION['teacher_success'];
                unset($_SESSION['teacher_success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['teacher_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php
                echo $_SESSION['teacher_error'];
                unset($_SESSION['teacher_error']);
                ?>
            </div>
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
            </form>
        </div>

        <!-- Attendance Statistics -->
        <?php if (!empty($attendance_stats) && $attendance_stats['total_students'] > 0): ?>
            <div class="stats-grid">
                <div class="stat-card stat-present">
                    <div class="stat-number"><?php echo $attendance_stats['present'] ?? 0; ?></div>
                    <div class="stat-label">Present</div>
                </div>
                <div class="stat-card stat-absent">
                    <div class="stat-number"><?php echo $attendance_stats['absent'] ?? 0; ?></div>
                    <div class="stat-label">Absent</div>
                </div>
                <div class="stat-card stat-late">
                    <div class="stat-number"><?php echo $attendance_stats['late'] ?? 0; ?></div>
                    <div class="stat-label">Late</div>
                </div>
                <div class="stat-card stat-excused">
                    <div class="stat-number"><?php echo $attendance_stats['excused'] ?? 0; ?></div>
                    <div class="stat-label">Excused</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Take Attendance -->
        <?php if (!empty($students)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-edit"></i> Take Attendance for <?php echo date('l, F d, Y', strtotime($date_filter)); ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="attendance.php" class="attendance-form">
                        <input type="hidden" name="class_id" value="<?php echo $class_filter; ?>">
                        <input type="hidden" name="date" value="<?php echo $date_filter; ?>">
                        
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
                            <button type="submit" name="take_attendance" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Attendance
                            </button>
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

    <script>
        // Add any JavaScript functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                });
            }, 5000);

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
        });
    </script>
</body>
</html> 