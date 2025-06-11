<?php
// Start session
session_start();

// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once '../config/config.php';

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

// Check if classes table exists
$result = $conn->query("SHOW TABLES LIKE 'classes'");
if ($result->num_rows == 0) {
    // Create classes table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        class_name VARCHAR(50) NOT NULL,
        grade_level VARCHAR(20) NOT NULL,
        teacher_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
    )");
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $class_id = intval($_GET['id']);
    
    // Delete the class
    $stmt = $conn->prepare("DELETE FROM classes WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $class_id, $school_id);
    
    if ($stmt->execute()) {
        $_SESSION['class_success'] = 'Class deleted successfully!';
    } else {
        $_SESSION['class_error'] = 'Failed to delete class: ' . $conn->error;
    }
    
    $stmt->close();
    header('Location: classes.php');
    exit;
}

// Get all classes for this school
$classes = [];
$stmt = $conn->prepare("SELECT c.*, t.name as teacher_name 
                       FROM classes c 
                       LEFT JOIN teachers t ON c.teacher_id = t.id 
                       WHERE c.school_id = ? 
                       ORDER BY c.grade_level ASC, c.class_name ASC");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();

// Get all teachers for dropdown
$teachers = [];
$stmt = $conn->prepare("SELECT id, name FROM teachers WHERE school_id = ? ORDER BY name ASC");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $teachers[] = $row;
}
$stmt->close();

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
    <title>Manage Classes - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR ?? '#00704a'; ?>;
            --footer-color: <?php echo FOOTER_COLOR ?? '#f8c301'; ?>;
            --accent-color: <?php echo ACCENT_COLOR ?? '#00704a'; ?>;
            --light-color: #ffffff;
            --dark-color: #333333;
            --gray-color: #f5f5f5;
            --border-color: #e0e0e0;
            --danger-color: #f44336;
            --sidebar-width: 250px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --radius-sm: 4px;
            --radius-md: 8px;
            --transition: all 0.3s ease;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        
        /* Card Styles */
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
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 112, 74, 0.1);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
            font-size: 1rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #d32f2f;
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
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: rgba(0, 112, 74, 0.05);
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            text-decoration: none;
        }
        
        .btn-icon.edit {
            background-color: var(--primary-color);
        }
        
        .btn-icon.delete {
            background-color: var(--danger-color);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 1rem;
        }
        
        .empty-text {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 1.5rem;
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
            
            .menu-item a span {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-school"></i> Manage Classes</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <span>Classes</span>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['class_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php 
                echo $_SESSION['class_success']; 
                unset($_SESSION['class_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['class_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php 
                echo $_SESSION['class_error']; 
                unset($_SESSION['class_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Add Class Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-plus-circle"></i> Add New Class</h2>
            </div>
            <div class="card-body">
                <form action="add_class.php" method="post">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="class_name">Class Name*</label>
                            <input type="text" id="class_name" name="class_name" class="form-control" placeholder="Enter class name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="grade_level">Grade Level*</label>
                            <select id="grade_level" name="grade_level" class="form-control" required>
                                <option value="">Select Grade Level</option>
                                <option value="Ordinary Level">Ordinary Level</option>
                                <option value="Advanced Level">Advanced Level</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="teacher_id">Class Teacher</label>
                            <select id="teacher_id" name="teacher_id" class="form-control">
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Add Class
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Classes List Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> All Classes</h2>
            </div>
            <div class="card-body">
                <?php if (count($classes) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>Grade Level</th>
                                    <th>Teacher</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                                        <td><?php echo htmlspecialchars($class['grade_level']); ?></td>
                                        <td><?php echo htmlspecialchars($class['teacher_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($class['created_at'])); ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="edit_class.php?id=<?php echo $class['id']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-edit"></i></a>
                                                <a href="classes.php?action=delete&id=<?php echo $class['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Are you sure you want to delete this class?');"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-school"></i></div>
                        <div class="empty-text">No classes found</div>
                        <p>Start by adding a new class using the form above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Class Information Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-info-circle"></i> Class Information</h2>
            </div>
            <div class="card-body">
                <p>Classes are the fundamental organizational units of your school. They help in:</p>
                <ul style="margin-top: 1rem; margin-left: 1.5rem;">
                    <li>Organizing students by grade level and subject</li>
                    <li>Assigning teachers to specific groups of students</li>
                    <li>Managing timetables and schedules</li>
                    <li>Tracking academic progress by group</li>
                    <li>Facilitating effective learning environments</li>
                </ul>
                <p style="margin-top: 1rem;">Add all your school classes to the system to keep track of class assignments and organization.</p>
            </div>
        </div>
    </div>
</body>
</html>