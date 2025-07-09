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

// Get assigned classes with detailed information
$assigned_classes = [];
try {
    $stmt = $conn->prepare('SELECT c.*, d.department_name, t.name as class_teacher_name,
                           (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) as student_count
                           FROM classes c 
                           LEFT JOIN departments d ON c.department_id = d.dep_id 
                           LEFT JOIN teachers t ON c.teacher_id = t.id
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
    <title>My Classes - Teacher Dashboard</title>
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

        /* Class Grid */
        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .class-card {
            background: var(--light-color);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .class-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .class-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }

        .class-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .class-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .class-grade {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .class-details {
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #666;
        }

        .detail-item i {
            width: 16px;
            color: var(--primary-color);
        }

        .class-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: var(--radius-sm);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
        }

        .class-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
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

            .class-grid {
                grid-template-columns: 1fr;
            }

            .class-actions {
                flex-direction: column;
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
            <h1 class="header-title">My Classes</h1>
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
            <h1><i class="fas fa-school"></i> My Assigned Classes</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Classes</span>
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

        <!-- Classes Overview -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Classes Overview</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div style="text-align: center; padding: 1rem; background: #e3f2fd; border-radius: var(--radius-sm);">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
                            <?php echo count($assigned_classes); ?>
                        </div>
                        <div style="color: #666; font-size: 0.9rem;">Total Classes</div>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: #e8f5e9; border-radius: var(--radius-sm);">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--accent-color);">
                            <?php echo array_sum(array_column($assigned_classes, 'student_count')); ?>
                        </div>
                        <div style="color: #666; font-size: 0.9rem;">Total Students</div>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: #fff3e0; border-radius: var(--radius-sm);">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color);">
                            <?php echo $teacher_info['department_name'] ?? 'N/A'; ?>
                        </div>
                        <div style="color: #666; font-size: 0.9rem;">Department</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Classes List -->
        <?php if (!empty($assigned_classes)): ?>
            <div class="class-grid">
                <?php foreach ($assigned_classes as $class): ?>
                    <div class="class-card">
                        <div class="class-header">
                            <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                            <div class="class-grade">Grade <?php echo htmlspecialchars($class['grade_level']); ?></div>
                        </div>
                        
                        <div class="class-details">
                            <div class="detail-item">
                                <i class="fas fa-building"></i>
                                <span><strong>Department:</strong> <?php echo htmlspecialchars($class['department_name'] ?? 'Not Assigned'); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span><strong>Class Teacher:</strong> <?php echo htmlspecialchars($class['class_teacher_name'] ?? 'Not Assigned'); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><strong>Room:</strong> <?php echo htmlspecialchars($class['room_number'] ?? 'Not Assigned'); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <span><strong>Schedule:</strong> <?php echo htmlspecialchars($class['schedule'] ?? 'Not Set'); ?></span>
                            </div>
                        </div>
                        
                        <div class="class-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $class['student_count']; ?></div>
                                <div class="stat-label">Students</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo htmlspecialchars($class['capacity'] ?? 'N/A'); ?></div>
                                <div class="stat-label">Capacity</div>
                            </div>
                        </div>
                        
                        <div class="class-actions">
                            <a href="class_details.php?id=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <a href="students.php?class_id=<?php echo $class['id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-user-graduate"></i> Students
                            </a>
                            <a href="marks.php?class_id=<?php echo $class['id']; ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-edit"></i> Manage Marks
                            </a>
                            <a href="attendance.php?class_id=<?php echo $class['id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-calendar-check"></i> Attendance
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-school"></i></div>
                        <div class="empty-text">No Classes Assigned</div>
                        <p>You haven't been assigned to any classes yet. Please contact your school administrator to get assigned to classes.</p>
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
    </script>
</body>
</html> 