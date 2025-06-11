<?php
// Start session
session_start();

// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once '../config/config.php';

// Add helper function for formatting currency if it doesn't exist
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '$' . number_format($amount, 2);
    }
}

// Add helper function for formatting dates if it doesn't exist
if (!function_exists('formatDate')) {
    function formatDate($date) {
        return date('M d, Y', strtotime($date));
    }
}

// Check if school admin is logged in
if (!isset($_SESSION['school_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get school_id from session
$school_id = $_SESSION['school_admin_school_id'] ?? 0;
if (!$school_id) {
    die("Error: School ID not found in session. Please log in again.");
}

// Get database connection
$conn = getDbConnection();

// Check database connection
if (!$conn) {
    die("Database connection failed");
}

// Initialize stats
$stats = [
    'students' => 0,
    'teachers' => 0,
    'classes' => 0,
    'bursars' => 0,
    'pending_payments' => 0,
    'total_revenue' => 0
];
$recent_students = [];
$recent_teachers = [];
$recent_payments = [];
$school_info = [];

// Get school info
try {
    $stmt = $conn->prepare('SELECT name, logo, address, phone, email FROM schools WHERE id = ?');
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $school_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching school info: " . $e->getMessage());
}

// Helper function to get count with improved error handling
function getCount($conn, $query, $school_id) {
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) return 0;
        $stmt->bind_param('i', $school_id);
        if (!$stmt->execute()) return 0;
        $res = $stmt->get_result();
        if (!$res) return 0;
        $count = $res->fetch_assoc()['count'] ?? 0;
        $stmt->close();
        return $count;
    } catch (Exception $e) {
        error_log("Error in getCount: " . $e->getMessage());
        return 0;
    }
}

// Fetch counts safely with table existence check
try {
    // Check if students table exists
    $result = $conn->query("SHOW TABLES LIKE 'students'");
    if ($result->num_rows > 0) {
        $stats['students'] = getCount($conn, 'SELECT COUNT(*) as count FROM students WHERE school_id = ?', $school_id);
}
    
    // Check if classes table exists
    $result = $conn->query("SHOW TABLES LIKE 'classes'");
    if ($result->num_rows > 0) {
        $stats['classes'] = getCount($conn, 'SELECT COUNT(*) as count FROM classes WHERE school_id = ?', $school_id);
    }
    
    // Check if teachers table exists
    $result = $conn->query("SHOW TABLES LIKE 'teachers'");
    if ($result->num_rows > 0) {
        $stats['teachers'] = getCount($conn, 'SELECT COUNT(*) as count FROM teachers WHERE school_id = ?', $school_id);
    }
    
    // Check if bursars table exists
    $result = $conn->query("SHOW TABLES LIKE 'bursars'");
    if ($result->num_rows > 0) {
        $stats['bursars'] = getCount($conn, 'SELECT COUNT(*) as count FROM bursars WHERE school_id = ?', $school_id);
    } else {
        // If bursars table doesn't exist, create it
        $conn->query("CREATE TABLE IF NOT EXISTS bursars (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
        )");
    }
    
    // Check if payments table exists
    $result = $conn->query("SHOW TABLES LIKE 'payments'");
    if ($result->num_rows > 0) {
        $stats['pending_payments'] = getCount($conn, 'SELECT COUNT(*) as count FROM payments WHERE school_id = ? AND status = "pending"', $school_id);
        
        // Get total revenue
        $stmt = $conn->prepare('SELECT SUM(amount) as total FROM payments WHERE school_id = ? AND status = "completed"');
        $stmt->bind_param('i', $school_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $stats['total_revenue'] = $res->fetch_assoc()['total'] ?? 0;
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching counts: " . $e->getMessage());
}

// Fetch recent students safely
try {
    $result = $conn->query("SHOW TABLES LIKE 'students'");
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare('SELECT id, first_name, last_name, admission_number, admission_date as created_at FROM students WHERE school_id = ? ORDER BY created_at DESC LIMIT 5');
        if ($stmt) {
            $stmt->bind_param('i', $school_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $row['name'] = $row['first_name'] . ' ' . $row['last_name'];
                $row['registration_number'] = $row['admission_number'];
                unset($row['first_name'], $row['last_name'], $row['admission_number']);
                $recent_students[] = $row;
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent students: " . $e->getMessage());
}

// Fetch recent teachers safely
try {
    $result = $conn->query("SHOW TABLES LIKE 'teachers'");
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare('SELECT name, email, created_at FROM teachers WHERE school_id = ? ORDER BY created_at DESC LIMIT 5');
        if ($stmt) {
            $stmt->bind_param('i', $school_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $recent_teachers = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent teachers: " . $e->getMessage());
}

// Fetch recent payments safely
try {
    $result = $conn->query("SHOW TABLES LIKE 'payments'");
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare('SELECT p.id, s.first_name, s.last_name, p.amount, p.payment_date, p.status 
                               FROM payments p
                               JOIN students s ON p.student_id = s.id
                               WHERE p.school_id = ?
                               ORDER BY p.payment_date DESC LIMIT 5');
        if ($stmt) {
            $stmt->bind_param('i', $school_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $row['student_name'] = $row['first_name'] . ' ' . $row['last_name'];
                unset($row['first_name'], $row['last_name']);
                $recent_payments[] = $row;
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent payments: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Admin Dashboard - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="enhanced-form-styles.css">
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR ?? '#00704a'; ?>;
            --footer-color: <?php echo FOOTER_COLOR ?? '#f8c301'; ?>;
            --accent-color: <?php echo ACCENT_COLOR ?? '#00704a'; ?>;
            --light-color: #ffffff;
            --dark-color: #333333;
            --gray-color: #f5f5f5;
            --border-color: #e0e0e0;
            --sidebar-width: 250px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: var(--gray-color);
            min-height: 100vh;
            display: flex;
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
        
        .sidebar-logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .sidebar-logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }
        
        .sidebar-logo span {
            color: var(--footer-color);
        }
        
        .sidebar-logo img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }
        
        .sidebar-logo span {
            color: var(--footer-color);
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
            padding: 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .breadcrumb span {
            margin: 0 0.5rem;
            color: #999;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: var(--light-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            background-color: var(--footer-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.8rem;
        }
        
        .stat-icon i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .stat-info h3 {
            font-size: 1.4rem;
            margin-bottom: 0.2rem;
            color: var(--primary-color);
        }
        
        .stat-info p {
            font-size: 0.9rem;
            color: #777;
        }
        
        /* Recent Cards */
        .card {
            background-color: var(--light-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 1.2rem;
            color: var (--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .card-header a:hover {
            color: var(--accent-color);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 0.8rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: rgba(0, 112, 74, 0.05);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 4px;
            background-color: var(--footer-color);
            color: var(--primary-color);
            text-decoration: none;
            margin-right: 0.5rem;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background-color: var(--primary-color);
            color: var(--light-color);
        }
        
        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid transparent;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }
        
        .alert-danger {
            background-color: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }
            
            .sidebar-header, .sidebar-user, .menu-heading {
                display: none;
            }
            
            .menu-item {
                padding: 1rem 0;
                justify-content: center;
            }
            
            .menu-item i {
                margin-right: 0;
                font-size: 1.3rem;
            }
            
            .menu-item a {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .main-content {
                padding: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Page Header with Quick Actions -->
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h1>School Admin Dashboard</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Home</a>
                    <span>/</span>
                    <span>Dashboard</span>
                </div>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                <button class="btn" onclick="openModal('addStudentMultiStepModal')" style="background-color: var(--primary-color); color: white; padding: 0.5rem 0.8rem; border-radius: 4px; text-decoration: none; display: flex; align-items: center; gap: 0.3rem; font-size: 0.85rem; border: none; cursor: pointer;">
                    <i class="fas fa-user-graduate"></i> Add Student
                </button>
                <button class="btn" onclick="openModal('addTeacherModal')" style="background-color: var(--primary-color); color: white; padding: 0.5rem 0.8rem; border-radius: 4px; text-decoration: none; display: flex; align-items: center; gap: 0.3rem; font-size: 0.85rem; border: none; cursor: pointer;">
                    <i class="fas fa-chalkboard-teacher"></i> Add Teacher
                </button>
                <button class="btn" onclick="openModal('addClassModal')" style="background-color: var(--primary-color); color: white; padding: 0.5rem 0.8rem; border-radius: 4px; text-decoration: none; display: flex; align-items: center; gap: 0.3rem; font-size: 0.85rem; border: none; cursor: pointer;">
                    <i class="fas fa-school"></i> Add Class
                </button>
                <button class="btn" onclick="openModal('addDepartmentModal')" style="background-color: var(--primary-color); color: white; padding: 0.5rem 0.8rem; border-radius: 4px; text-decoration: none; display: flex; align-items: center; gap: 0.3rem; font-size: 0.85rem; border: none; cursor: pointer;">
                    <i class="fas fa-building"></i> Add Dept
                </button>
            </div>
        </div>
        
        <!-- Alert Messages -->
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['teacher_success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['teacher_success']; 
                unset($_SESSION['teacher_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['teacher_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['teacher_error']; 
                unset($_SESSION['teacher_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['student_success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['student_success']; 
                unset($_SESSION['student_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['student_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['student_error']; 
                unset($_SESSION['student_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['bursar_success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['bursar_success']; 
                unset($_SESSION['bursar_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['bursar_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['bursar_error']; 
                unset($_SESSION['bursar_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['logo_success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['logo_success']; 
                unset($_SESSION['logo_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['logo_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['logo_error']; 
                unset($_SESSION['logo_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['students']; ?></h3>
                    <p>Students</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['teachers']; ?></h3>
                    <p>Teachers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-school"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['classes']; ?></h3>
                    <p>Classes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-info">
                    <h3><?php 
                    // Check if departments table exists and count departments
                    $dept_count = 0;
                    try {
                        $result = $conn->query("SHOW TABLES LIKE 'departments'");
                        if ($result->num_rows > 0) {
                            $dept_count = getCount($conn, 'SELECT COUNT(*) as count FROM departments WHERE school_id = ?', $school_id);
                        }
                    } catch (Exception $e) {
                        error_log("Error counting departments: " . $e->getMessage());
                    }
                    echo $dept_count;
                    ?></h3>
                    <p>Departments</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['bursars']; ?></h3>
                    <p>Bursars</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo formatCurrency($stats['total_revenue']); ?></h3>
                    <p>Revenue</p>
                </div>
            </div>
        </div>
        
        <!-- Recent Students -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-user-graduate mr-2"></i> Recent Students</h2>
                <a href="students.php">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Registration Number</th>
                                <th>Registration Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_students)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No recent students found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['registration_number']); ?></td>
                                        <td><?php echo formatDate($student['created_at']); ?></td>
                                        <td>
                                            <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="action-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="view_student.php?id=<?php echo $student['id']; ?>" class="action-btn" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Recent Payments -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-credit-card mr-2"></i> Recent Payments</h2>
                <a href="payments.php">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_payments)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No recent payments found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['student_name']); ?></td>
                                        <td><?php echo formatCurrency($payment['amount']); ?></td>
                                        <td><?php echo formatDate($payment['payment_date']); ?></td>
                                        <td>
                                            <?php if ($payment['status'] === 'completed'): ?>
                                                <span class="status-badge status-completed">Completed</span>
                                            <?php elseif ($payment['status'] === 'pending'): ?>
                                                <span class="status-badge status-pending">Pending</span>
                                            <?php else: ?>
                                                <span class="status-badge status-inactive">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view_payment.php?id=<?php echo $payment['id']; ?>" class="action-btn" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- School Overview -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-info-circle mr-2"></i> School Overview</h2>
            </div>
            <div class="card-body">
                <p>Welcome to the <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?> Administration Dashboard. From here, you can manage all students, teachers, classes, and monitor school activities.</p>
                <p>Use the sidebar navigation to access different sections of the admin panel.</p>
                
                <?php if ($stats['pending_payments'] > 0): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background-color: #fff3cd; border-radius: 4px;">
                        <p style="margin: 0; color: #856404;"><strong>Attention:</strong> You have <?php echo $stats['pending_payments']; ?> payment(s) pending review. <a href="payments.php?status=pending" style="color: #856404; text-decoration: underline;">Review now</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Example: Departments Table -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-building"></i> Departments</h2>
                <a href="#" onclick="openModal('addDepartmentModal')" class="btn btn-primary" style="font-size:0.9rem;"><i class="fas fa-plus"></i> Add Department</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Department Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch departments for this school
                            $departments = [];
                            try {
                                $stmt = $conn->prepare("SELECT * FROM departments WHERE school_id = ? ORDER BY department_name ASC");
                                $stmt->bind_param('i', $school_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()) {
                                    $departments[] = $row;
                                }
                                $stmt->close();
                            } catch (Exception $e) {
                                error_log("Error fetching departments: " . $e->getMessage());
                            }
                            ?>
                            <?php if (empty($departments)): ?>
                                <tr>
                                    <td colspan="2" style="text-align:center;">No departments found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($departments as $department): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($department['department_name']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-primary edit-department-btn" data-id="<?php echo $department['dep_id']; ?>" title="Edit"><i class="fas fa-edit"></i></button>
                                            <button type="button" class="btn btn-danger delete-department-btn" data-id="<?php echo $department['dep_id']; ?>" title="Delete"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Example: Classes Table -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-school"></i> Classes</h2>
                <a href="#" onclick="openModal('addClassModal')" class="btn btn-primary" style="font-size:0.9rem;"><i class="fas fa-plus"></i> Add Class</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Grade Level</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch classes for this school
                            $classes = [];
                            try {
                                $stmt = $conn->prepare("SELECT * FROM classes WHERE school_id = ? ORDER BY grade_level ASC, class_name ASC");
                                $stmt->bind_param('i', $school_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()) {
                                    $classes[] = $row;
                                }
                                $stmt->close();
                            } catch (Exception $e) {
                                error_log("Error fetching classes: " . $e->getMessage());
                            }
                            ?>
                            <?php if (empty($classes)): ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;">No classes found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                        <td><?php echo htmlspecialchars($class['grade_level']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-primary edit-class-btn" data-id="<?php echo $class['id']; ?>" title="Edit"><i class="fas fa-edit"></i></button>
                                            <button type="button" class="btn btn-danger delete-class-btn" data-id="<?php echo $class['id']; ?>" title="Delete"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    
   <!-- Modal for Add Student -->
    <!-- Remove confirmation dialog and allow direct form submission -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-graduate"></i> Add New Student</h2>
                <span class="close-modal" data-modal="studentModal">&times;</span>
            </div>
            <div class="modal-body">
                <form action="add_student.php" method="POST" class="modal-form">
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-user"></i> Personal Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" id="first_name" name="first_name" class="form-control" placeholder="Enter first name" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" id="last_name" name="last_name" class="form-control" placeholder="Enter last name" required>
                            </div>
                            <div class="form-group">
                                <label for="class">Class <span class="required">*</span></label>
                                <input type="text" id="class" name="class" class="form-control" placeholder="Enter class" required>
                            </div>
                            <div class="form-group">
                                <label for="department_id">Department</label>
                                <select id="department_id" name="department_id" class="form-control">
                                    <option value="">Select Department</option>
                                    <?php
                                    // Get all departments for this school
                                    try {
                                        $stmt = $conn->prepare("SELECT * FROM departments WHERE school_id = ? ORDER BY department_name ASC");
                                        $stmt->bind_param('i', $school_id);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<option value="' . $row['dep_id'] . '">' . htmlspecialchars($row['department_name']) . '</option>';
                                        }
                                        $stmt->close();
                                    } catch (Exception $e) {
                                        error_log("Error fetching departments: " . $e->getMessage());
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" class="form-control">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="dob">Date of Birth</label>
                                <input type="date" id="dob" name="dob" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-users"></i> Parent/Guardian Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="parent_name">Parent/Guardian Name <span class="required">*</span></label>
                                <input type="text" id="parent_name" name="parent_name" class="form-control" placeholder="Enter parent/guardian name" required>
                            </div>
                            <div class="form-group">
                                <label for="parent_phone">Parent/Guardian Phone <span class="required">*</span></label>
                                <input type="tel" id="parent_phone" name="parent_phone" class="form-control" placeholder="Enter parent/guardian phone" required>
                            </div>
                            <div class="form-group">
                                <label for="parent_email">Parent/Guardian Email</label>
                                <input type="email" id="parent_email" name="parent_email" class="form-control" placeholder="Enter parent/guardian email">
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" class="form-control" rows="3" placeholder="Enter address"></textarea>
                            </div>
                            
</div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add Student</button>
                        <button type="button" class="btn btn-secondary close-modal" data-modal="studentModal"><i class="fas fa-times-circle"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal forms are included from modals.php -->
    
    <!-- Modal forms are included at the end of the body -->

    <!-- Include Student Registration Modal -->
    <?php include 'student_registration_modal.php'; ?>
    
    <!-- Add Teacher Modal -->
    <div id="addTeacherModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> Add New Teacher</h2>
                <span class="close-modal" onclick="closeModal('addTeacherModal')">&times;</span>
            </div>
            <div class="modal-body">
                <?php if (isset($_SESSION['teacher_error'])): ?>
                    <div class="form-alert form-alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['teacher_error']; unset($_SESSION['teacher_error']); ?>
                    </div>
                <?php endif; ?>
                
                <form action="add_teacher.php" method="POST" class="modal-form" id="teacherForm">
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-id-card"></i> Teacher Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="teacher_name">Full Name <span class="required">*</span></label>
                                <input type="text" id="teacher_name" name="teacher_name" class="form-control" required>
                                <div id="teacher_name-error" class="error-message"></div>
                            </div>
                            <div class="form-group">
                                <label for="teacher_email">Email <span class="required">*</span></label>
                                <input type="email" id="teacher_email" name="teacher_email" class="form-control" required>
                                <div id="teacher_email-error" class="error-message"></div>
                            </div>
                            <div class="form-group">
                                <label for="teacher_phone">Phone <span class="required">*</span></label>
                                <input type="tel" id="teacher_phone" name="teacher_phone" class="form-control" required>
                                <div id="teacher_phone-error" class="error-message"></div>
                            </div>
                            <div class="form-group">
                                <label for="teacher_subject">Subject</label>
                                <input type="text" id="teacher_subject" name="teacher_subject" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="teacher_qualification">Qualification</label>
                                <input type="text" id="teacher_qualification" name="teacher_qualification" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="teacher_department_id">Department <span class="required">*</span></label>
                                <select id="teacher_department_id" name="department_id" class="form-control" required>
                                    <option value="">Select Department</option>
                                    <?php 
                                    // Get all departments for dropdown
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
                                    
                                    foreach ($departments as $department): ?>
                                        <option value="<?php echo $department['dep_id']; ?>">
                                            <?php echo htmlspecialchars($department['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="teacher_department_id-error" class="error-message"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="saveTeacherBtn"><i class="fas fa-save"></i> Save Teacher</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addTeacherModal')"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Class Modal -->
    <div id="addClassModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-door-open"></i> Add New Class</h2>
                <span class="close-modal" onclick="closeModal('addClassModal')">&times;</span>
            </div>
            <div class="modal-body">
                <?php if (isset($_SESSION['class_error'])): ?>
                    <div class="form-alert form-alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['class_error']; unset($_SESSION['class_error']); ?>
                    </div>
                <?php endif; ?>
                
                <form action="add_class.php" method="POST" class="modal-form" id="classForm">
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-school"></i> Class Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="class_name">Class Name <span class="required">*</span></label>
                                <input type="text" id="class_name" name="class_name" class="form-control" required>
                                <div id="class_name-error" class="error-message"></div>
                            </div>
                            <div class="form-group">
                                <label for="grade_level">Grade Level <span class="required">*</span></label>
                                <select id="grade_level" name="grade_level" class="form-control" required>
                                    <option value="">Select Grade Level</option>
                                    <option value="Ordinary Level">Ordinary Level</option>
                                    <option value="Advanced Level">Advanced Level</option>
                                </select>
                                <div id="grade_level-error" class="error-message"></div>
                            </div>
                            <div class="form-group">
                                <label for="teacher_id">Class Teacher</label>
                                <select id="teacher_id" name="teacher_id" class="form-control">
                                    <option value="">Select Teacher</option>
                                    <?php 
                                    // Get all teachers for dropdown
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
                                    
                                    foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="saveClassBtn"><i class="fas fa-save"></i> Save Class</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addClassModal')"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div id="addDepartmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-building"></i> Add New Department</h2>
                <span class="close-modal" onclick="closeModal('addDepartmentModal')">&times;</span>
            </div>
            <div class="modal-body">
                <?php if (isset($_SESSION['department_error'])): ?>
                    <div class="form-alert form-alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['department_error']; unset($_SESSION['department_error']); ?>
                    </div>
                <?php endif; ?>
                
                <form action="departments.php" method="POST" class="modal-form" id="departmentForm">
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-building"></i> Department Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="department_name">Department Name <span class="required">*</span></label>
                                <input type="text" id="department_name" name="department_name" class="form-control" required>
                                <div id="department_name-error" class="error-message"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="saveDepartmentBtn"><i class="fas fa-save"></i> Save Department</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addDepartmentModal')"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.style.overflow = 'auto'; // Restore scrolling
    }
    
    // Function to open modal
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
    }

// Handle update/delete for departments and classes
document.addEventListener('DOMContentLoaded', function() {
    // Confirmation dialog for Teacher form
    document.getElementById('teacherForm').addEventListener('submit', function(event) {
        event.preventDefault();
        if (confirm('Are you sure you want to add this teacher?')) {
            this.submit();
        }
    });
    
    // Confirmation dialog for Class form
    document.getElementById('classForm').addEventListener('submit', function(event) {
        event.preventDefault();
        if (confirm('Are you sure you want to add this class?')) {
            this.submit();
        }
    });
    
    // Confirmation dialog for Department form
    document.getElementById('departmentForm').addEventListener('submit', function(event) {
        event.preventDefault();
        if (confirm('Are you sure you want to add this department?')) {
            this.submit();
        }
    });
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (modals[i].style.display === 'block') {
                    modals[i].style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            }
        }
    });
    // Department Delete
    document.querySelectorAll('.delete-department-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (confirm('Are you sure you want to delete this department?')) {
                window.location.href = 'delete_department.php?id=' + btn.dataset.id;
            }
        });
    });

    // Department Edit
    document.querySelectorAll('.edit-department-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            // You can open a modal or redirect to edit page
            window.location.href = 'edit_department.php?id=' + btn.dataset.id;
        });
    });

    // Class Delete
    document.querySelectorAll('.delete-class-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (confirm('Are you sure you want to delete this class?')) {
                window.location.href = 'delete_class.php?id=' + btn.dataset.id;
            }
        });
    });

    // Class Edit
    document.querySelectorAll('.edit-class-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            // You can open a modal or redirect to edit page
            window.location.href = 'edit_class.php?id=' + btn.dataset.id;
        });
    });
});
</script>

    <!-- Custom JS -->
    <script src="js/modal-handler.js"></script>
    <script src="js/enhanced-features.js"></script>
    <script src="js/search.js"></script>
    <script src="js/multi-step-form.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // No need for duplicate event listeners since we're using onclick attributes
        // The openModal function is already defined in modal-handler.js
        
        // Initialize any other dashboard-specific functionality here
    });
    </script>
</body>
</html>