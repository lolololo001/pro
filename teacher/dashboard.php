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

// Check database connection
if (!$conn) {
    die("Database connection failed");
}

// Initialize stats
$stats = [
    'total_classes' => 0,
    'total_students' => 0,
    'total_subjects' => 0,
    'pending_marks' => 0
];

$teacher_info = [];
$assigned_classes = [];
$recent_activities = [];

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

// Get assigned classes
try {
    $stmt = $conn->prepare('SELECT c.*, d.department_name, 
                           (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count
                           FROM classes c 
                           LEFT JOIN departments d ON c.department_id = d.dep_id 
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

// Calculate stats
$stats['total_classes'] = count($assigned_classes);
$stats['total_students'] = array_sum(array_column($assigned_classes, 'student_count'));
$stats['total_subjects'] = !empty($teacher_info['subject']) ? count(explode(',', $teacher_info['subject'])) : 0;

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
    <title>Teacher Dashboard - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card.all-classes {
            border-left: 4px solid var(--primary-color);
        }

        .stat-card.total-students {
            border-left: 4px solid var(--accent-color);
        }

        .stat-card.subjects {
            border-left: 4px solid var(--info-color);
        }

        .stat-card.pending-marks {
            border-left: 4px solid var(--warning-color);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--light-color);
        }

        .stat-card.all-classes .stat-icon {
            background: var(--primary-color);
        }

        .stat-card.total-students .stat-icon {
            background: var(--accent-color);
        }

        .stat-card.subjects .stat-icon {
            background: var(--info-color);
        }

        .stat-card.pending-marks .stat-icon {
            background: var(--warning-color);
        }

        .stat-info h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .stat-info p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        /* Card Styles */
        .card {
            background: var(--light-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            overflow: hidden;
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

        /* Class List */
        .class-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .class-item:hover {
            box-shadow: var(--shadow-sm);
            border-color: var(--primary-color);
        }

        .class-info {
            flex: 1;
        }

        .class-name {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .class-details {
            font-size: 0.85rem;
            color: #666;
        }

        .class-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }

        /* Recent Activities */
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .activity-content h4 {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            color: var(--dark-color);
        }

        .activity-content p {
            font-size: 0.8rem;
            color: #666;
            margin: 0;
        }

        .activity-time {
            font-size: 0.75rem;
            color: #999;
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

            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
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

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <h1 class="header-title">Teacher Dashboard</h1>
        </div>
        <div class="header-actions">
            <a href="profile.php" class="btn btn-secondary">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="../logout.php" class="btn btn-primary">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-chalkboard-teacher"></i> Welcome, <?php echo htmlspecialchars($teacher_info['name'] ?? 'Teacher'); ?>!</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Home</span>
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

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card all-classes" onclick="window.location.href='classes.php'">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-school"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_classes']; ?></h3>
                        <p>Assigned Classes</p>
                    </div>
                </div>
            </div>

            <div class="stat-card total-students" onclick="window.location.href='students.php'">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_students']; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
            </div>

            <div class="stat-card subjects">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_subjects']; ?></h3>
                        <p>Subjects Taught</p>
                    </div>
                </div>
            </div>

            <div class="stat-card pending-marks" onclick="window.location.href='marks.php'">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_marks']; ?></h3>
                        <p>Pending Marks</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Assigned Classes -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-school"></i> My Assigned Classes</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($assigned_classes)): ?>
                        <?php foreach ($assigned_classes as $class): ?>
                            <div class="class-item">
                                <div class="class-info">
                                    <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                    <div class="class-details">
                                        <i class="fas fa-users"></i> <?php echo $class['student_count']; ?> students
                                        <span style="margin: 0 0.5rem;">•</span>
                                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($class['department_name'] ?? 'No Department'); ?>
                                        <span style="margin: 0 0.5rem;">•</span>
                                        <i class="fas fa-layer-group"></i> Grade <?php echo htmlspecialchars($class['grade_level']); ?>
                                    </div>
                                </div>
                                <div class="class-actions">
                                    <a href="class_details.php?id=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="marks.php?class_id=<?php echo $class['id']; ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-edit"></i> Marks
                                    </a>
                                    <a href="attendance.php?class_id=<?php echo $class['id']; ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-calendar-check"></i> Attendance
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-school"></i></div>
                            <div class="empty-text">No Classes Assigned</div>
                            <p>You haven't been assigned to any classes yet. Please contact your school administrator.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activities & Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <a href="marks.php" class="btn btn-primary" style="justify-content: center;">
                            <i class="fas fa-edit"></i> Manage Marks
                        </a>
                        <a href="attendance.php" class="btn btn-secondary" style="justify-content: center;">
                            <i class="fas fa-calendar-check"></i> Take Attendance
                        </a>
                        <a href="reports.php" class="btn btn-secondary" style="justify-content: center;">
                            <i class="fas fa-chart-bar"></i> View Reports
                        </a>
                        <a href="profile.php" class="btn btn-secondary" style="justify-content: center;">
                            <i class="fas fa-user-cog"></i> Update Profile
                        </a>
                    </div>

                    <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color);">

                    <h4 style="margin-bottom: 1rem; color: var(--dark-color);">
                        <i class="fas fa-info-circle"></i> Teacher Information
                    </h4>
                    
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: var(--radius-sm);">
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Name:</strong> <?php echo htmlspecialchars($teacher_info['name'] ?? 'N/A'); ?>
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Email:</strong> <?php echo htmlspecialchars($teacher_info['email'] ?? 'N/A'); ?>
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Phone:</strong> <?php echo htmlspecialchars($teacher_info['phone'] ?? 'N/A'); ?>
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Subject:</strong> <?php echo htmlspecialchars($teacher_info['subject'] ?? 'N/A'); ?>
                        </div>
                        <div style="margin-bottom: 0.5rem;">
                            <strong>Department:</strong> <?php echo htmlspecialchars($teacher_info['department_name'] ?? 'N/A'); ?>
                        </div>
                        <div>
                            <strong>Qualification:</strong> <?php echo htmlspecialchars($teacher_info['qualification'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
    </script>
</body>
</html> 