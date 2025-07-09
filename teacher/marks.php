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
    if (isset($_POST['add_marks'])) {
        $student_id = intval($_POST['student_id']);
        $subject = trim($_POST['subject']);
        $marks = floatval($_POST['marks']);
        $max_marks = floatval($_POST['max_marks']);
        $term = trim($_POST['term']);
        $year = intval($_POST['year']);
        $comments = trim($_POST['comments'] ?? '');

        // Validate inputs
        if ($marks < 0 || $marks > $max_marks) {
            $_SESSION['teacher_error'] = 'Marks cannot be negative or exceed maximum marks.';
        } else {
            try {
                // Check if marks already exist for this student, subject, term, and year
                $check_stmt = $conn->prepare("SELECT id FROM student_marks WHERE student_id = ? AND subject = ? AND term = ? AND year = ?");
                $check_stmt->bind_param('issi', $student_id, $subject, $term, $year);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing marks
                    $update_stmt = $conn->prepare("UPDATE student_marks SET marks = ?, max_marks = ?, comments = ?, updated_at = NOW() WHERE student_id = ? AND subject = ? AND term = ? AND year = ?");
                    $update_stmt->bind_param('ddsssi', $marks, $max_marks, $comments, $student_id, $subject, $term, $year);
                    
                    if ($update_stmt->execute()) {
                        $_SESSION['teacher_success'] = 'Marks updated successfully!';
                    } else {
                        $_SESSION['teacher_error'] = 'Failed to update marks: ' . $conn->error;
                    }
                    $update_stmt->close();
                } else {
                    // Insert new marks
                    $insert_stmt = $conn->prepare("INSERT INTO student_marks (student_id, subject, marks, max_marks, term, year, comments, teacher_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $insert_stmt->bind_param('isddssi', $student_id, $subject, $marks, $max_marks, $term, $year, $comments, $teacher_id);
                    
                    if ($insert_stmt->execute()) {
                        $_SESSION['teacher_success'] = 'Marks added successfully!';
                    } else {
                        $_SESSION['teacher_error'] = 'Failed to add marks: ' . $conn->error;
                    }
                    $insert_stmt->close();
                }
                $check_stmt->close();
            } catch (Exception $e) {
                $_SESSION['teacher_error'] = 'System error: ' . $e->getMessage();
            }
        }
        
        header('Location: marks.php');
        exit;
    }
}

// Create student_marks table if it doesn't exist
try {
    $conn->query("CREATE TABLE IF NOT EXISTS student_marks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        subject VARCHAR(100) NOT NULL,
        marks DECIMAL(5,2) NOT NULL,
        max_marks DECIMAL(5,2) NOT NULL DEFAULT 100,
        term VARCHAR(20) NOT NULL,
        year INT NOT NULL,
        comments TEXT,
        teacher_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
        UNIQUE KEY unique_marks (student_id, subject, term, year)
    )");
} catch (Exception $e) {
    error_log("Error creating student_marks table: " . $e->getMessage());
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
$student_filter = $_GET['student_id'] ?? '';
$subject_filter = $_GET['subject'] ?? '';
$term_filter = $_GET['term'] ?? '';

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

// Get students based on filter
$students = [];
if (!empty($class_filter)) {
    try {
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
    } catch (Exception $e) {
        error_log("Error fetching students: " . $e->getMessage());
    }
}

// Get existing marks
$marks_data = [];
if (!empty($student_filter)) {
    try {
        $stmt = $conn->prepare('SELECT sm.*, s.first_name, s.last_name, s.admission_number
                               FROM student_marks sm
                               JOIN students s ON sm.student_id = s.id
                               WHERE sm.student_id = ?
                               ORDER BY sm.term ASC, sm.year DESC, sm.subject ASC');
        $stmt->bind_param('i', $student_filter);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $marks_data[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching marks: " . $e->getMessage());
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
    <title>Manage Marks - Teacher Dashboard</title>
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

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-full {
            grid-column: 1 / -1;
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .data-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid var(--border-color);
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-warning {
            background: #fff3e0;
            color: #f57c00;
        }

        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }

        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
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
            <h1 class="header-title">Manage Marks</h1>
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
            <h1><i class="fas fa-edit"></i> Manage Student Marks</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Marks</span>
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
            <form method="GET" action="marks.php" class="filter-form">
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
                
                <?php if (!empty($students)): ?>
                    <div class="form-group">
                        <label for="student_filter">Select Student</label>
                        <select name="student_id" id="student_filter" class="form-control" onchange="this.form.submit()">
                            <option value="">Choose Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" 
                                        <?php echo $student_filter == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> 
                                    (<?php echo htmlspecialchars($student['admission_number']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Add Marks Form -->
        <?php if (!empty($student_filter) && !empty($students)): ?>
            <?php 
            $selected_student = null;
            foreach ($students as $student) {
                if ($student['id'] == $student_filter) {
                    $selected_student = $student;
                    break;
                }
            }
            ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plus"></i> Add/Update Marks for <?php echo htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']); ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="marks.php">
                        <input type="hidden" name="student_id" value="<?php echo $student_filter; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="subject">Subject <span style="color: red;">*</span></label>
                                <input type="text" name="subject" id="subject" class="form-control" required 
                                       placeholder="e.g., Mathematics, English, Science">
                            </div>
                            
                            <div class="form-group">
                                <label for="term">Term <span style="color: red;">*</span></label>
                                <select name="term" id="term" class="form-control" required>
                                    <option value="">Select Term</option>
                                    <option value="First Term">First Term</option>
                                    <option value="Second Term">Second Term</option>
                                    <option value="Third Term">Third Term</option>
                                    <option value="Mid Term">Mid Term</option>
                                    <option value="Final">Final</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="year">Year <span style="color: red;">*</span></label>
                                <select name="year" id="year" class="form-control" required>
                                    <option value="">Select Year</option>
                                    <?php 
                                    $current_year = date('Y');
                                    for ($i = $current_year - 2; $i <= $current_year + 1; $i++) {
                                        echo "<option value=\"$i\">$i</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="marks">Marks Obtained <span style="color: red;">*</span></label>
                                    <input type="number" name="marks" id="marks" class="form-control" required 
                                           step="0.01" min="0" placeholder="e.g., 85.5">
                                </div>
                                
                                <div class="form-group">
                                    <label for="max_marks">Maximum Marks</label>
                                    <input type="number" name="max_marks" id="max_marks" class="form-control" 
                                           value="100" step="0.01" min="1" placeholder="e.g., 100">
                                </div>
                            </div>
                            
                            <div class="form-group form-full">
                                <label for="comments">Comments (Optional)</label>
                                <textarea name="comments" id="comments" class="form-control" rows="3" 
                                          placeholder="Add any comments about the student's performance..."></textarea>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1.5rem; text-align: right;">
                            <button type="submit" name="add_marks" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Marks
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Existing Marks -->
        <?php if (!empty($marks_data)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Existing Marks</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Term</th>
                                    <th>Year</th>
                                    <th>Marks</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                    <th>Comments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($marks_data as $mark): ?>
                                    <?php 
                                    $percentage = ($mark['marks'] / $mark['max_marks']) * 100;
                                    $grade = '';
                                    if ($percentage >= 90) $grade = 'A+';
                                    elseif ($percentage >= 80) $grade = 'A';
                                    elseif ($percentage >= 70) $grade = 'B';
                                    elseif ($percentage >= 60) $grade = 'C';
                                    elseif ($percentage >= 50) $grade = 'D';
                                    else $grade = 'F';
                                    
                                    $grade_class = '';
                                    if ($percentage >= 80) $grade_class = 'badge-success';
                                    elseif ($percentage >= 60) $grade_class = 'badge-warning';
                                    else $grade_class = 'badge-danger';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($mark['subject']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($mark['term']); ?></td>
                                        <td><?php echo htmlspecialchars($mark['year']); ?></td>
                                        <td>
                                            <strong><?php echo $mark['marks']; ?></strong> / <?php echo $mark['max_marks']; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $grade_class; ?>">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $grade_class; ?>">
                                                <?php echo $grade; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($mark['comments'] ?? 'No comments'); ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button class="btn btn-secondary btn-sm" onclick="editMark(<?php echo $mark['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="deleteMark(<?php echo $mark['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif (!empty($student_filter)): ?>
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-clipboard-list"></i></div>
                        <div class="empty-text">No Marks Found</div>
                        <p>No marks have been recorded for this student yet. Use the form above to add marks.</p>
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
                        <p>Please select a class from the dropdown above to start managing marks.</p>
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
        });

        function editMark(markId) {
            // Implement edit functionality
            alert('Edit functionality will be implemented here for mark ID: ' + markId);
        }

        function deleteMark(markId) {
            if (confirm('Are you sure you want to delete this mark? This action cannot be undone.')) {
                // Implement delete functionality
                alert('Delete functionality will be implemented here for mark ID: ' + markId);
            }
        }
    </script>
</body>
</html> 