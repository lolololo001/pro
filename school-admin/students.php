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

// Fetch recent students (top 5)
$recent_students = [];
try {
    $stmt = $conn->prepare('SELECT id, first_name, last_name, admission_number, reg_number, class, created_at FROM students WHERE school_id = ? ORDER BY created_at DESC LIMIT 5');
    if ($stmt) {
        $stmt->bind_param('i', $school_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $recent_students[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching recent students: " . $e->getMessage());
}

// Check if students table exists
$result = $conn->query("SHOW TABLES LIKE 'students'");
if ($result->num_rows == 0) {
    // Create students table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        class VARCHAR(20) NOT NULL,
        gender VARCHAR(10),
        dob DATE,
        parent_name VARCHAR(100) NOT NULL,
        parent_phone VARCHAR(20) NOT NULL,
        parent_email VARCHAR(100),
        address TEXT,
        status ENUM('active', 'inactive', 'graduated', 'shifted') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
    )");
}



// Check if department_id column exists in students table
$department_column_exists = false;
$columns_result = $conn->query("SHOW COLUMNS FROM students LIKE 'department_id'");
if ($columns_result && $columns_result->num_rows > 0) {
    $department_column_exists = true;
}

// Check if status column exists in students table
$status_column_exists = false;
$status_columns_result = $conn->query("SHOW COLUMNS FROM students LIKE 'status'");
if ($status_columns_result && $status_columns_result->num_rows > 0) {
    $status_column_exists = true;
} else {
    // Add status column if it doesn't exist
    $conn->query("ALTER TABLE students ADD COLUMN status ENUM('active', 'inactive', 'graduated', 'shifted') DEFAULT 'active'");
    $status_column_exists = true;
}

// Get all classes for this school
$classes = [];
$stmt = $conn->prepare("SELECT * FROM classes WHERE school_id = ? ORDER BY grade_level ASC, class_name ASC");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();

// Get all departments for this school
$departments = [];
$stmt = $conn->prepare("SELECT * FROM departments WHERE school_id = ? ORDER BY department_name ASC");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}
$stmt->close();

// Get student counts by status
$student_counts = [
    'all' => 0,
    'active' => 0,
    'inactive' => 0,
    'graduated' => 0,
    'shifted' => 0
];

try {
    // Get total count
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM students WHERE school_id = ?');
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_counts['all'] = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    // Get active count
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM students WHERE school_id = ? AND status = "active"');
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_counts['active'] = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    // Get inactive count
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM students WHERE school_id = ? AND status = "inactive"');
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_counts['inactive'] = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    // Get graduated count
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM students WHERE school_id = ? AND status = "graduated"');
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_counts['graduated'] = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    
    // Get shifted count
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM students WHERE school_id = ? AND status = "shifted"');
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_counts['shifted'] = $result->fetch_assoc()['count'] ?? 0;
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching student counts: " . $e->getMessage());
}

// Get all students for this school with department and class information
$students = [];

// Get status filter from URL if set
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$where_clause = "s.school_id = ?";
$params = [$school_id];

// Add status filter to query if selected
if (!empty($status_filter) && in_array($status_filter, ['active', 'inactive', 'graduated', 'shifted'])) {
    $where_clause .= " AND s.status = ?";
    $params[] = $status_filter;
}

if ($department_column_exists) {
    $query = "SELECT s.*, d.department_name, c.class_name, c.grade_level 
             FROM students s 
             LEFT JOIN departments d ON s.department_id = d.dep_id 
             LEFT JOIN classes c ON s.class_id = c.id 
             WHERE $where_clause 
             ORDER BY s.last_name ASC, s.first_name ASC";
    $stmt = $conn->prepare($query);
} else {
    // If department_id column doesn't exist, don't join with departments
    $query = "SELECT s.*, NULL as department_name, c.class_name, c.grade_level 
             FROM students s 
             LEFT JOIN classes c ON s.class_id = c.id 
             WHERE $where_clause 
             ORDER BY s.last_name ASC, s.first_name ASC";
    $stmt = $conn->prepare($query);
}

// Bind parameters based on the number of parameters
if (count($params) === 1) {
    $stmt->bind_param('i', $params[0]);
} else if (count($params) === 2) {
    $stmt->bind_param('is', $params[0], $params[1]);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
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

// Don't close connection yet - needed for modal includes
// $conn->close();
?>
<?php if (!empty($_SESSION['student_success'])): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    let msg = "<?php echo addslashes($_SESSION['student_success']); ?>";
    <?php if (!empty($_SESSION['api_message_status'])): ?>
        msg += "\n<?php echo addslashes($_SESSION['api_message_status']); ?>";
    <?php endif; ?>
    alert(msg); // Use a better popup if you want (e.g. SweetAlert)
});
</script>
<?php
unset($_SESSION['student_success']);
unset($_SESSION['api_message_status']);
endif;
if (!empty($_SESSION['student_error'])): ?>
<script>
window.addEventListener('DOMContentLoaded', function() {
    alert("<?php echo addslashes($_SESSION['student_error']); ?>");
});
</script>
<?php unset($_SESSION['student_error']); endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="enhanced-features.css">
    <link rel="stylesheet" href="css/universal-confirmation.css">
    <link rel="stylesheet" href="enhanced-form-styles.css">
    <link rel="stylesheet" href="css/enhanced-modal.css">

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
            padding: 1.5rem;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            width: 100vw;
            max-width: 100vw;
            box-sizing: border-box;
            overflow-x: hidden;
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
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            width: 100%;
            min-width: 0;
            border: 1px solid rgba(0, 112, 74, 0.1);
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

        .export-btn:hover {
            background-color: #e9ecef !important;
            border-color: #adb5bd !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .data-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            table-layout: auto;
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
        @media (max-width: 1200px) {
            .main-content {
                width: 100vw;
                max-width: 100vw;
                margin-left: 0;
                padding: 1rem;
            }
            .card {
                width: 100vw;
                max-width: 100vw;
            }
            .table-responsive {
                width: 100vw;
                max-width: 100vw;
            }
            .data-table {
                min-width: 700px;
            }
        }
        @media (max-width: 768px) {
            .main-content {
                width: 100vw;
                max-width: 100vw;
                margin-left: 0;
                padding: 0.5rem;
            }
            .card {
                width: 100vw;
                max-width: 100vw;
            }
            .table-responsive {
                width: 100vw;
                max-width: 100vw;
            }
            .data-table {
                min-width: 500px;
            }
            .data-table th,
            .data-table td {
                padding: 0.5rem;
                font-size: 0.95rem;
                word-break: break-word;
            }
        }
        
        
        /* Search Box Styles */
        .search-container {
            margin-bottom: 1.5rem;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background-color: white;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.5rem 1rem;
            box-shadow: var(--shadow-sm);
        }
        
        .search-icon {
            color: var(--primary-color);
            margin-right: 0.5rem;
        }
        
        .search-input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 1rem;
            padding: 0.25rem 0;
        }
        
        .search-clear {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .search-clear:hover {
            color: var(--danger-color);
        }


        
        .empty-search-results {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .empty-search-results i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #ccc;
        }
        
        .highlight {
            background-color: rgba(248, 195, 1, 0.3);
            padding: 2px;
            border-radius: 2px;
        }
        
        /* Status Filter Styles */
        .status-filter-container {
            padding: 0 1.5rem;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }
        
        .status-filter-label {
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .status-filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .status-btn {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            text-decoration: none;
            color: var(--dark-color);
            background-color: var(--gray-color);
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }
        
        .status-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .status-btn.active {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
        }
        
        .status-btn.status-active {
            border-left: 3px solid #4caf50;
        }
        
        .status-btn.status-inactive {
            border-left: 3px solid #ff9800;
        }
        
        .status-btn.status-graduated {
            border-left: 3px solid #2196f3;
        }
        
        .status-btn.status-shifted {
            border-left: 3px solid #9c27b0;
        }
        
        /* Status Badge Styles */
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.6rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
        }
        
        .status-badge.status-active {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        
        .status-badge.status-inactive {
            background-color: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }
        
        .status-badge.status-graduated {
            background-color: rgba(33, 150, 243, 0.1);
            color: #2196f3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }
        
        .status-badge.status-shifted {
            background-color: rgba(156, 39, 176, 0.1);
            color: #9c27b0;
            border: 1px solid rgba(156, 39, 176, 0.3);
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
                margin-left: 70px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .card-header > div {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                width: 100%;
            }

            .search-container {
                margin-top: 1rem;
                width: 100%;
                max-width: 100%;
            }

            .search-box {
                max-width: 100%;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                max-width: 100%;
            }

            .status-btn {
                font-size: 0.8rem !important;
                padding: 0.4rem 0.8rem !important;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                margin-left: 0;
                padding: 0.5rem;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .search-input {
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .card-header > div {
                flex-direction: column;
                gap: 1rem;
            }

            .status-btn {
                font-size: 0.75rem !important;
                padding: 0.3rem 0.6rem !important;
            }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
            width: 100%;
            overflow-x: auto;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            transition: opacity 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .stat-card:hover::before {
            opacity: 0.15;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.5rem;
        }

        .stat-info {
            flex: 1;
        }

        .stat-info h3 {
            font-size: 1.8rem;
            margin: 0;
            font-weight: 600;
        }

        .stat-info p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        /* Card variants */
        .all-students {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid #e9ecef;
        }

        .all-students .stat-icon {
            color: var(--primary-color);
            background: rgba(var(--primary-rgb), 0.1);
        }

        .active-students {
            background: linear-gradient(135deg, #ffffff 0%, #e8f5e9 100%);
            border: 1px solid #c8e6c9;
        }

        .active-students .stat-icon {
            color: #4caf50;
            background: rgba(76, 175, 80, 0.1);
        }

        .inactive-students {
            background: linear-gradient(135deg, #ffffff 0%, #fff3e0 100%);
            border: 1px solid #ffe0b2;
        }

        .inactive-students .stat-icon {
            color: #ff9800;
            background: rgba(255, 152, 0, 0.1);
        }

        .graduated-students {
            background: linear-gradient(135deg, #ffffff 0%, #e3f2fd 100%);
            border: 1px solid #bbdefb;
        }

        .graduated-students .stat-icon {
            color: #2196f3;
            background: rgba(33, 150, 243, 0.1);
        }

        .shifted-students {
            background: linear-gradient(135deg, #ffffff 0%, #f3e5f5 100%);
            border: 1px solid #e1bee7;
        }

        .shifted-students .stat-icon {
            color: #9c27b0;
            background: rgba(156, 39, 176, 0.1);
        }





        /* Smooth transitions for table rows */
        .data-table tbody tr {
            transition: opacity 0.2s ease;
        }

        /* Alert animations */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Smooth transitions for table rows */
        .data-table tbody tr {
            transition: opacity 0.2s ease;
        }
    </style>
    <script src="js/modal-handler.js"></script>
    <script src="js/multi-step-form.js"></script>
    <script src="js/student-management.js"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-user-graduate"></i> Manage Students</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <span>Students</span>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['student_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php 
                echo $_SESSION['student_success']; 
                unset($_SESSION['student_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['student_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php 
                echo $_SESSION['student_error']; 
                unset($_SESSION['student_error']);
                ?>
            </div>
        <?php endif; ?>
        <!-- Student Status Cards -->
        <div class="stats-grid">
            <div class="stat-card all-students" onclick="window.location.href='students.php'">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $student_counts['all']; ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            
            <div class="stat-card active-students" onclick="window.location.href='students.php?status=active'">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $student_counts['active']; ?></h3>
                    <p>Active</p>
                </div>
            </div>
            
            <div class="stat-card inactive-students" onclick="window.location.href='students.php?status=inactive'">
                <div class="stat-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $student_counts['inactive']; ?></h3>
                    <p>Inactive</p>
                </div>
            </div>
            
            <div class="stat-card graduated-students" onclick="window.location.href='students.php?status=graduated'">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $student_counts['graduated']; ?></h3>
                    <p>Graduated</p>
                </div>
            </div>
            
            <div class="stat-card shifted-students" onclick="window.location.href='students.php?status=shifted'">
                <div class="stat-icon">
                    <i class="fas fa-user-slash"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $student_counts['shifted']; ?></h3>
                    <p>Shifted</p>
                </div>
            </div>
        </div>
    


        <!-- Students List Card -->
        <div id="all-students" class="card" style="border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.1); overflow: hidden; border: none; margin-top: 2rem;">
            <div class="card-header">
                <h2><i class="fas fa-graduation-cap"></i> All Students</h2>
                <!-- Action buttons positioned at the right -->
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <button onclick="exportStudentData()" class="btn export-btn" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; font-size: 0.9rem; border-radius: 6px; background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057; transition: all 0.3s ease;">
                        <i class="fas fa-download"></i> Export List
                    </button>
                    <button class="btn btn-primary" onclick="openModal('addStudentMultiStepModal')" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; font-size: 0.9rem; border-radius: 6px; transition: all 0.3s ease;">
                        <i class="fas fa-user-plus"></i> Add New Student
                    </button>
                </div>
            </div>

            <div class="card-body">
                <?php if (count($students ?? []) > 0): ?>
                    <!-- Search Box -->
                    <div class="search-container" data-table="students-table">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="studentSearch" class="search-input" placeholder="Search students by name, reg number, class, parent, or contact..." onkeyup="searchStudents()">
                            <button type="button" class="search-clear" onclick="clearStudentSearch()" title="Clear search">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Status Filter Buttons -->
                    <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="font-weight: 600; color: var(--primary-color); font-size: 0.95rem;">Filter by Status:</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <a href="students.php" class="status-btn <?php echo empty($_GET['status']) ? 'active' : ''; ?>" style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.85rem; text-decoration: none; background-color: <?php echo empty($_GET['status']) ? 'var(--primary-color)' : '#f0f0f0'; ?>; color: <?php echo empty($_GET['status']) ? 'white' : 'var(--dark-color)'; ?>; transition: all 0.3s ease; border: none; font-weight: 500;">
                                <i class="fas fa-users"></i> All
                            </a>
                            <a href="students.php?status=active" class="status-btn status-active <?php echo isset($_GET['status']) && $_GET['status'] === 'active' ? 'active' : ''; ?>" style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.85rem; text-decoration: none; color: <?php echo isset($_GET['status']) && $_GET['status'] === 'active' ? 'white' : '#4caf50'; ?>; background-color: <?php echo isset($_GET['status']) && $_GET['status'] === 'active' ? '#4caf50' : 'rgba(76, 175, 80, 0.1)'; ?>; transition: all 0.3s ease; border: none; font-weight: 500;">
                                <i class="fas fa-user-check"></i> Active
                            </a>
                            <a href="students.php?status=inactive" class="status-btn status-inactive <?php echo isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'active' : ''; ?>" style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.85rem; text-decoration: none; color: <?php echo isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'white' : '#ff9800'; ?>; background-color: <?php echo isset($_GET['status']) && $_GET['status'] === 'inactive' ? '#ff9800' : 'rgba(255, 152, 0, 0.1)'; ?>; transition: all 0.3s ease; border: none; font-weight: 500;">
                                <i class="fas fa-user-clock"></i> Inactive
                            </a>
                            <a href="students.php?status=graduated" class="status-btn status-graduated <?php echo isset($_GET['status']) && $_GET['status'] === 'graduated' ? 'active' : ''; ?>" style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.85rem; text-decoration: none; color: <?php echo isset($_GET['status']) && $_GET['status'] === 'graduated' ? 'white' : '#2196f3'; ?>; background-color: <?php echo isset($_GET['status']) && $_GET['status'] === 'graduated' ? '#2196f3' : 'rgba(33, 150, 243, 0.1)'; ?>; transition: all 0.3s ease; border: none; font-weight: 500;">
                                <i class="fas fa-graduation-cap"></i> Graduated
                            </a>
                            <a href="students.php?status=shifted" class="status-btn status-shifted <?php echo isset($_GET['status']) && $_GET['status'] === 'shifted' ? 'active' : ''; ?>" style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.85rem; text-decoration: none; color: <?php echo isset($_GET['status']) && $_GET['status'] === 'shifted' ? 'white' : '#9c27b0'; ?>; background-color: <?php echo isset($_GET['status']) && $_GET['status'] === 'shifted' ? '#9c27b0' : 'rgba(156, 39, 176, 0.1)'; ?>; transition: all 0.3s ease; border: none; font-weight: 500;">
                                <i class="fas fa-exchange-alt"></i> Shifted
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table" id="students-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Reg Number</th>
                                    <th>Class</th>
                                    <th>Department</th>
                                    <th>Gender</th>
                                    <th>Parent/Guardian</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr id="student-row-<?php echo $student['id']; ?>" data-id="<?php echo $student['id']; ?>">
                                        <td><span class="student-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span></td>
                                        <td><?php echo htmlspecialchars($student['reg_number'] ?? 'N/A'); ?></td>
                                        <td><?php 
                                            if (!empty($student['grade_level']) && !empty($student['class_name'])) {
                                                echo htmlspecialchars($student['grade_level'] . ' - ' . $student['class_name']);
                                            } else {
                                                echo 'Not assigned';
                                            }
                                        ?></td>
                                        <td><?php echo htmlspecialchars($student['department_name'] ?? 'Not assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($student['gender'] ?? 'Not specified'); ?></td>
                                        <td><?php echo htmlspecialchars($student['parent_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($student['parent_phone']); ?><br>
                                            <small><?php echo htmlspecialchars($student['parent_email'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <div class="status-container" style="position: relative;">
                                                <select class="status-dropdown-select" data-student-id="<?php echo $student['id']; ?>" style="padding: 0.3rem 0.7rem; border-radius: 20px; border: 1px solid #ccc; font-size: 0.95rem; background: #fff; min-width: 110px;">
                                                    <option value="active" <?php if($student['status']==='active') echo 'selected'; ?>>Active</option>
                                                    <option value="inactive" <?php if($student['status']==='inactive') echo 'selected'; ?>>Inactive</option>
                                                    <option value="graduated" <?php if($student['status']==='graduated') echo 'selected'; ?>>Graduated</option>
                                                    <option value="shifted" <?php if($student['status']==='shifted') echo 'selected'; ?>>Shifted</option>
                                                </select>
                                            </div>
    <script>
    // Handle status dropdown change
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.status-dropdown-select').forEach(function(select) {
            select.addEventListener('change', function() {
                var studentId = this.getAttribute('data-student-id');
                var newStatus = this.value;
                this.disabled = true;
                var original = this;
                fetch('update_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(studentId) + '&type=student&status=' + encodeURIComponent(newStatus)
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        original.style.background = '#e8f5e9';
                        setTimeout(() => { original.style.background = '#fff'; }, 1000);
                        // Remove row if filter is active and status no longer matches
                        var urlParams = new URLSearchParams(window.location.search);
                        var filterStatus = urlParams.get('status');
                        if (filterStatus && filterStatus !== newStatus) {
                            // Remove the row from the table
                            var row = original.closest('tr');
                            if (row) {
                                row.style.transition = 'opacity 0.3s';
                                row.style.opacity = '0';
                                setTimeout(function() {
                                    row.remove();
                                    searchStudents && searchStudents();
                                }, 300);
                            }
                        } else if (filterStatus === 'shifted' && newStatus === 'shifted') {
                            // If filter is shifted and status is shifted, update the row visually (optional: show a badge or highlight)
                            var row = original.closest('tr');
                            if (row) {
                                row.style.background = '#f3e6fa'; // light purple highlight
                                setTimeout(() => { row.style.background = ''; }, 1200);
                            }
                        }
                        // Optionally show a toast/alert
                        if (typeof showAlert === 'function') {
                            showAlert('success', '<i class="fas fa-check-circle"></i> Status updated to ' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
                        }
                    } else {
                        alert('Failed to update status: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(() => {
                    alert('Failed to update status.');
                })
                .finally(() => {
                    original.disabled = false;
                });
            });
        });
    });
    </script>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="javascript:void(0)"
                                                   class="btn-icon edit"
                                                   title="Edit <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                   data-id="<?php echo $student['id']; ?>"
                                                   data-type="student"
                                                   data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                   data-modal="true">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="javascript:void(0)"
                                                   class="btn-icon delete"
                                                   title="Delete <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                                   data-id="<?php echo $student['id']; ?>"
                                                   data-type="student"
                                                   data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Empty search results message -->
                        <div id="students-table-empty-search" class="empty-search-results" style="display: none;">
                            <i class="fas fa-search"></i>
                            <p>No students found matching your search criteria.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
                        <div class="empty-text">No students found</div>
                        <p>We don't have informations you're searching for</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    

    <style>
        /* Status Badge Styles */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
            transition: all 0.3s ease;
        }
        
        .status-badge i {
            margin-right: 6px;
            font-size: 0.9rem;
        }
        
        .status-active {
            background-color: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        
        .status-active:hover {
            background-color: rgba(76, 175, 80, 0.25);
        }
        
        .status-inactive {
            background-color: rgba(255, 152, 0, 0.15);
            color: #ff9800;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }
        
        .status-inactive:hover {
            background-color: rgba(255, 152, 0, 0.25);
        }
        
        .status-graduated {
            background-color: rgba(33, 150, 243, 0.15);
            color: #2196f3;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }
        
        .status-graduated:hover {
            background-color: rgba(33, 150, 243, 0.25);
        }
        
        .status-shifted {
            background-color: rgba(156, 39, 176, 0.15);
            color: #9c27b0;
            border: 1px solid rgba(156, 39, 176, 0.3);
        }
        
        .status-shifted:hover {
            background-color: rgba(156, 39, 176, 0.25);
        }
        
        /* Status Dropdown Styles */
        .status-dropdown {
            position: absolute;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 150px;
            overflow: hidden;
            animation: fadeIn 0.2s ease;
            display: none; /* Ensure dropdown is hidden by default */
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .status-option {
            padding: 0.8rem 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-option:hover {
            background-color: #f5f5f5;
        }
        
        .status-option i {
            width: 16px;
        }
        
        .status-option:not(:last-child) {
            border-bottom: 1px solid #eee;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 80%;
            max-width: 800px;
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .modal-header {
            padding: 1rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .close-modal {
            color: white;
            font-size: 1.8rem;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #f8c301;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 2rem;
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .loading-spinner i {
            margin-right: 10px;
        }
        
        /* Form Styles for Modal */
        .modal-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .form-section {
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Alert animations */
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes slideOutRight {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100px); }
        }
    </style>
    

      <script>
        // Global variables
        let currentStudentId = null;
        let confirmCallback = null;
        let debounceTimeout = null;





        // Export functionality
        function exportStudentData() {
            const table = document.querySelector('.data-table');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cols = row.querySelectorAll('td, th');
                    const rowData = [];
                    cols.forEach(col => {
                        // Clean the text content
                        let text = col.textContent.replace(/(\r\n|\n|\r)/gm, '').trim();
                        // Escape quotes and wrap in quotes if contains comma
                        text = text.includes(',') ? `"${text.replace(/"/g, '""')}"` : text;
                        rowData.push(text);
                    });
                    csv.push(rowData.join(','));
                }
            });

            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.setAttribute('download', 'students_list.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Function to open modal
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        // Function to close modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
        
        // Open student edit form
        function openStudentEditForm(studentId) {
            currentStudentId = studentId;
            const modal = document.getElementById('studentEditModal');
            const formContainer = document.getElementById('studentEditFormContainer');
            
            // Show loading spinner
            formContainer.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            modal.style.display = 'block';
            
            // Fetch student data
            fetch(`get_edit_form.php?type=student&id=${studentId}`)
                .then(response => response.text())
                .then(html => {
                    // If the returned HTML is empty or just whitespace, show a fallback message
                    if (!html || html.trim() === '' || html.trim() === '<form id="editForm"></form>') {
                        // Render a basic fallback form with some editable fields
                        formContainer.innerHTML = `
                            <form id="editForm">
                                <div class="modal-form">
                                    <div class="form-section">
                                        <div class="enhanced-form-group">
                                            <label class="enhanced-label" for="first_name"><i class="fas fa-user"></i> First Name</label>
                                            <input type="text" class="form-control enhanced-input" id="first_name" name="first_name" value="" required>
                                        </div>
                                        <div class="enhanced-form-group">
                                            <label class="enhanced-label" for="last_name"><i class="fas fa-user"></i> Last Name</label>
                                            <input type="text" class="form-control enhanced-input" id="last_name" name="last_name" value="" required>
                                        </div>
                                        <div class="enhanced-form-group">
                                            <label class="enhanced-label" for="reg_number"><i class="fas fa-id-card"></i> Registration Number</label>
                                            <input type="text" class="form-control enhanced-input" id="reg_number" name="reg_number" value="" required>
                                        </div>
                                        <div class="enhanced-form-group">
                                            <label class="enhanced-label" for="parent_name"><i class="fas fa-user-friends"></i> Parent/Guardian Name</label>
                                            <input type="text" class="form-control enhanced-input" id="parent_name" name="parent_name" value="" required>
                                        </div>
                                        <div class="enhanced-form-group">
                                            <label class="enhanced-label" for="parent_phone"><i class="fas fa-phone"></i> Parent Phone</label>
                                            <input type="text" class="form-control enhanced-input" id="parent_phone" name="parent_phone" value="" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn"><i class="fas fa-save"></i> Save Changes</button>
                                    <button type="button" class="btn btn-danger" onclick="closeStudentEditModal()"><i class="fas fa-times"></i> Cancel</button>
                                </div>
                            </form>
                        `;
                        // Add event listener to the fallback form
                        const form = document.getElementById('editForm');
                        if (form) {
                            form.addEventListener('submit', function(e) {
                                e.preventDefault();
                                confirmUpdateStudent(new FormData(form));
                            });
                        }
                        return;
                    }
                    formContainer.innerHTML = html;
                    // Add event listener to the form
                    const form = document.getElementById('editForm');
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            confirmUpdateStudent(new FormData(form));
                        });
                    }
                })
                .catch(error => {
                    formContainer.innerHTML = `<div class=\"alert alert-danger\">Error loading form: ${error.message}</div>`;
                });
        }
        
        // Close student edit modal
        function closeStudentEditModal() {
            document.getElementById('studentEditModal').style.display = 'none';
            currentStudentId = null;
        }
        
        // Confirm student update
        function confirmUpdateStudent(formData) {
            const modal = document.getElementById('confirmationModal');
            const message = document.getElementById('confirmationMessage');
            const confirmBtn = document.getElementById('confirmButton');
            message.innerHTML = 'Are you sure you want to update this student information?';
            modal.style.display = 'block';
            confirmCallback = function() {
                updateStudent(formData);
                closeConfirmationModal();
            };
            confirmBtn.onclick = function() {
                if (confirmCallback) confirmCallback();
            };
        }
        
        // Update student
        function updateStudent(formData) {
            if (!currentStudentId) return;

            // Show loading state in confirmation modal
            const confirmBtn = document.getElementById('confirmButton');
            const originalContent = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            confirmBtn.disabled = true;

            // Add student ID to form data
            formData.append('id', currentStudentId);
            formData.append('type', 'student');

            // Send update request to universal handler
            fetch('update_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Close modals
                closeConfirmationModal();
                closeStudentEditModal();

                if (data.success) {
                    // Show success message
                    showAlert('success', '<i class="fas fa-check-circle"></i> ' + data.message);

                    // Reload the page to show updated data after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('danger', '<i class="fas fa-exclamation-circle"></i> Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', '<i class="fas fa-exclamation-circle"></i> Error updating student: ' + error.message);
            })
            .finally(() => {
                // Restore button state
                confirmBtn.innerHTML = originalContent;
                confirmBtn.disabled = false;
            });
        }
        
        // Confirm delete student
        function confirmDeleteStudent(studentId) {
            currentStudentId = studentId;
            const modal = document.getElementById('confirmationModal');
            const message = document.getElementById('confirmationMessage');
            const confirmBtn = document.getElementById('confirmButton');
            
            message.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #f44336;"></i> Are you sure you want to delete this student? This action cannot be undone.';
            modal.style.display = 'block';
            
            // Set up the confirm button action
            confirmCallback = function() {
                // Actually delete the student after confirmation
                fetch('delete_handler.php', {
                    method: 'POST',
                    body: (() => {
                        const formData = new FormData();
                        formData.append('id', studentId);
                        formData.append('type', 'student');
                        return formData;
                    })()
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the student row from the table
                        const studentRow = document.getElementById(`student-row-${studentId}`);
                        if (studentRow) {
                            studentRow.style.transition = 'opacity 0.3s';
                            studentRow.style.opacity = '0';
                            setTimeout(() => {
                                studentRow.remove();
                                // Update search results counter
                                if (typeof searchStudents === 'function') searchStudents();
                            }, 300);
                        }
                        showAlert('success', '<i class="fas fa-check-circle"></i> Student deleted successfully.');
                    } else {
                        showAlert('danger', '<i class="fas fa-exclamation-circle"></i> Error: ' + (data.message || 'Failed to delete student'));
                    }
                })
                .catch(error => {
                    showAlert('danger', '<i class="fas fa-exclamation-circle"></i> An error occurred while deleting the student.');
                })
                .finally(() => {
                    closeConfirmationModal();
                });
            };
            
            confirmBtn.onclick = function() {
                if (confirmCallback) confirmCallback();
            };
        }
        
        // Delete student
        function deleteStudent(studentId) {
            // Show loading state
            const confirmBtn = document.getElementById('confirmButton');
            const originalContent = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            confirmBtn.disabled = true;

            // Create form data
            const formData = new FormData();
            formData.append('id', studentId);
            formData.append('type', 'student');

            // Send delete request via AJAX to universal handler
            fetch('delete_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close confirmation modal
                    closeConfirmationModal();

                    // Remove the student row from the table
                    const studentRow = document.getElementById(`student-row-${studentId}`);
                    if (studentRow) {
                        studentRow.style.transition = 'opacity 0.3s ease';
                        studentRow.style.opacity = '0';
                        setTimeout(() => {
                            studentRow.remove();
                            // Update search results counter
                            searchStudents();
                        }, 300);
                    }

                    // Show success message
                    showAlert('success', '<i class="fas fa-check-circle"></i> ' + data.message);
                } else {
                    // Show error message
                    showAlert('danger', '<i class="fas fa-exclamation-circle"></i> Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', '<i class="fas fa-exclamation-circle"></i> An error occurred while deleting the student.');
            })
            .finally(() => {
                // Restore button state
                confirmBtn.innerHTML = originalContent;
                confirmBtn.disabled = false;
                closeConfirmationModal();
            });
        }
        
        // Close confirmation modal
        function closeConfirmationModal() {
            document.getElementById('confirmationModal').style.display = 'none';
            confirmCallback = null;
        }

        // Show alert function
        function showAlert(type, message) {
            // Remove any existing alerts
            const existingAlerts = document.querySelectorAll('.alert-notification');
            existingAlerts.forEach(alert => alert.remove());

            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-notification`;
            alertDiv.innerHTML = message;
            alertDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                max-width: 500px;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideInRight 0.3s ease;
                background-color: ${type === 'success' ? '#d4edda' : '#f8d7da'};
                color: ${type === 'success' ? '#155724' : '#721c24'};
                border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
            `;

            // Add to page
            document.body.appendChild(alertDiv);

            // Auto remove after 5 seconds
            setTimeout(() => {
                alertDiv.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 300);
            }, 5000);
        }
        
        // AJAX form submission handler
        function submitFormWithAjax(form, successCallback) {
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalContent = submitBtn.innerHTML;

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showAlert('success', data.message);
                    
                    // Remove the alert after 3 seconds
                    setTimeout(function() {
                        alertDiv.remove();
                    }, 3000);
                } else {
                    // Show error and revert to original content
                    statusBadge.innerHTML = originalContent;
                    showAlert('danger', '<i class="fas fa-exclamation-circle"></i> ' + (data.message || 'Failed to update student status'));
                }
            })
            .catch(error => {
                // Restore original content on error
                statusBadge.innerHTML = originalContent;
                showAlert('danger', '<i class="fas fa-exclamation-circle"></i> Network error occurred while updating status');
            });
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.status-container')) {
                document.querySelectorAll('.status-dropdown').forEach(function(dropdown) {
                    dropdown.style.display = 'none';
                });
            }
        });
        
        // Simple and effective student search functionality
        function searchStudents() {
            const searchTerm = document.getElementById('studentSearch').value.toLowerCase().trim();
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            const emptyMessage = document.getElementById('students-table-empty-search');
            const tableContainer = document.querySelector('.table-responsive');
            let visibleCount = 0;

            tableRows.forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                const regNumber = row.cells[1].textContent.toLowerCase();
                const className = row.cells[2].textContent.toLowerCase();
                const department = row.cells[3] ? row.cells[3].textContent.toLowerCase() : '';
                const gender = row.cells[4] ? row.cells[4].textContent.toLowerCase() : '';
                const parentName = row.cells[5] ? row.cells[5].textContent.toLowerCase() : '';
                const contact = row.cells[6] ? row.cells[6].textContent.toLowerCase() : '';

                // Check if any field contains the search term
                const matchesSearch = searchTerm === '' ||
                    name.includes(searchTerm) ||
                    regNumber.includes(searchTerm) ||
                    className.includes(searchTerm) ||
                    department.includes(searchTerm) ||
                    gender.includes(searchTerm) ||
                    parentName.includes(searchTerm) ||
                    contact.includes(searchTerm);

                if (matchesSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide empty search message
            if (visibleCount === 0 && searchTerm !== '') {
                if (emptyMessage) emptyMessage.style.display = 'block';
                if (tableContainer) tableContainer.style.display = 'none';
            } else {
                if (emptyMessage) emptyMessage.style.display = 'none';
                if (tableContainer) tableContainer.style.display = '';
            }
        }
        
        // Clear student search
        function clearStudentSearch() {
            const searchInput = document.getElementById('studentSearch');
            searchInput.value = '';
            searchStudents();
            searchInput.focus();
        }
        
        // Update empty search results message
        function updateEmptySearchResults(visibleCount) {
            const tableBody = document.querySelector('.data-table tbody');
            let emptyMessage = document.querySelector('.empty-search-results');
            
            if (visibleCount === 0) {
                if (!emptyMessage) {
                    emptyMessage = document.createElement('div');
                    emptyMessage.className = 'empty-search-results';
                    emptyMessage.innerHTML = `
                        <i class="fas fa-search"></i>
                        <p>No students found matching your search criteria</p>
                    `;
                    tableBody.parentNode.insertBefore(emptyMessage, tableBody.nextSibling);
                }
                tableBody.style.display = 'none';
                emptyMessage.style.display = 'block';
            } else {
                tableBody.style.display = '';
                if (emptyMessage) {
                    emptyMessage.style.display = 'none';
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize search functionality
            const searchInput = document.getElementById('studentSearch');
            if (searchInput) {
                // Clear search when clicking the search button
                const searchBtn = document.querySelector('.search-clear');
                if (searchBtn) {
                    searchBtn.addEventListener('click', clearStudentSearch);
                }

                // Add keyboard shortcuts
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        clearStudentSearch();
                        e.preventDefault();
                    }
                });
            }

            // Initialize action buttons
            const editButtons = document.querySelectorAll('.btn-icon.edit');
            editButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const studentId = this.getAttribute('data-id');
                    const studentName = this.getAttribute('data-name');

                    // Use the universal edit modal system if available
                    if (typeof showEditModal === 'function') {
                        showEditModal('student', studentId, studentName);
                    } else {
                        // Fallback to local function
                        openStudentEditForm(studentId);
                    }
                });
            });

            // Initialize delete buttons
            const deleteButtons = document.querySelectorAll('.btn-icon.delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const studentId = this.getAttribute('data-id');
                    const studentName = this.getAttribute('data-name');

                    // Use the universal delete confirmation system if available
                    if (typeof showDeleteConfirmation === 'function') {
                        showDeleteConfirmation('student', studentId, '', '', studentName);
                    } else {
                        // Fallback to local function
                        confirmDeleteStudent(studentId);
                    }
                });
            });
        });
        
        // Function to highlight matching text
        function highlightText(element, searchTerm) {
            // First remove any existing highlights
            removeHighlights(element);
            
            // Get all text nodes within the element
            const walk = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null, false);
            const textNodes = [];
            let node;
            
            while (node = walk.nextNode()) {
                textNodes.push(node);
            }
            
            // Process each text node
            textNodes.forEach(textNode => {
                const content = textNode.nodeValue;
                const lowerContent = content.toLowerCase();
                let position = lowerContent.indexOf(searchTerm);
                
                if (position !== -1) {
                    const span = document.createElement('span');
                    span.className = 'highlight';
                    
                    const before = document.createTextNode(content.substring(0, position));
                    const match = document.createTextNode(content.substring(position, position + searchTerm.length));
                    const after = document.createTextNode(content.substring(position + searchTerm.length));
                    
                    span.appendChild(match);
                    
                    const fragment = document.createDocumentFragment();
                    fragment.appendChild(before);
                    fragment.appendChild(span);
                    fragment.appendChild(after);
                    
                    textNode.parentNode.replaceChild(fragment, textNode);
                }
            });
        }
        
        // Function to remove highlights
        function removeHighlights(element) {
            const highlights = element.querySelectorAll('.highlight');
            highlights.forEach(highlight => {
                const parent = highlight.parentNode;
                const textNode = document.createTextNode(highlight.textContent);
                parent.replaceChild(textNode, highlight);
                parent.normalize(); // Combine adjacent text nodes
            });
        }

        // Function to show alert messages
        function showAlert(type, message) {
            // Remove any existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());

            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = message;
            alertDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 300px;
                max-width: 500px;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideInRight 0.3s ease;
            `;

            // Add to page
            document.body.appendChild(alertDiv);

            // Auto remove after 5 seconds
            setTimeout(() => {
                alertDiv.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 300);
            }, 5000);
        }

        // Export student data function
        function exportStudentData() {
            const visibleRows = document.querySelectorAll('.data-table tbody tr:not([style*="display: none"])');

            if (visibleRows.length === 0) {
                showAlert('warning', '<i class="fas fa-exclamation-triangle"></i> No students to export. Please adjust your search or filter criteria.');
                return;
            }

            // Prepare CSV data
            const headers = ['Name', 'Registration Number', 'Class', 'Department', 'Gender', 'Parent/Guardian', 'Contact', 'Status'];
            let csvContent = headers.join(',') + '\n';

            visibleRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = [];

                // Extract text content from each cell (excluding the actions column)
                for (let i = 0; i < cells.length - 1; i++) {
                    let cellText = cells[i].textContent.trim();
                    // Escape commas and quotes in CSV
                    if (cellText.includes(',') || cellText.includes('"')) {
                        cellText = '"' + cellText.replace(/"/g, '""') + '"';
                    }
                    rowData.push(cellText);
                }

                csvContent += rowData.join(',') + '\n';
            });

            // Create and download file
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');

            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);

                // Generate filename with current date and filter status
                const currentDate = new Date().toISOString().split('T')[0];
                const statusFilter = new URLSearchParams(window.location.search).get('status') || 'all';
                const filename = `students_${statusFilter}_${currentDate}.csv`;

                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                showAlert('success', `<i class="fas fa-check-circle"></i> Successfully exported ${visibleRows.length} students to ${filename}`);
            } else {
                showAlert('danger', '<i class="fas fa-exclamation-circle"></i> Export not supported in this browser.');
            }
        }
    </script>
    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Confirm Action</h2>
                <span class="close-modal" onclick="closeConfirmationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="confirmationMessage" style="margin-bottom: 1.5rem; font-size: 1.1rem; line-height: 1.5;"></div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" onclick="closeConfirmationModal()" style="background-color: #6c757d; color: white; padding: 0.75rem 1.5rem;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" id="confirmButton" class="btn btn-danger" style="padding: 0.75rem 1.5rem;">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Edit Modal -->
    <div id="studentEditModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit Student</h2>
                <span class="close-modal" onclick="closeStudentEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="studentEditFormContainer">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Loading student information...
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Include Student Registration Modal - Same as Dashboard -->
    <?php include 'student_registration_modal.php'; ?>

    <?php
    // Close database connection
    $conn->close();
    ?>

    <!-- Custom CSS for Student Registration Modal -->
    <style>
        /* Fix icon spacing and placeholder positioning */
        .input-wrapper {
            position: relative;
        }

        .input-wrapper .enhanced-input,
        .select-wrapper .enhanced-select,
        .textarea-wrapper .enhanced-textarea {
            padding-left: 45px !important; /* Add space for icon */
            padding-right: 15px;
        }

        .input-icon,
        .select-icon,
        .textarea-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #333333; /* Black icons */
            pointer-events: none;
            z-index: 2;
            transition: color 0.3s ease;
        }

        .input-wrapper:hover .input-icon,
        .select-wrapper:hover .select-icon,
        .textarea-wrapper:hover .textarea-icon {
            color: #00704a; /* Green on hover */
        }

        /* Ensure placeholders don't overlap with icons */
        .enhanced-input::placeholder,
        .enhanced-select option:first-child,
        .enhanced-textarea::placeholder {
            color: #6c757d;
            opacity: 0.7;
        }

        /* Fix Save Student button styling to match dashboard */
        #modalSubmitBtn {
            background: #00704a !important; /* Green background matching dashboard */
            border: 1px solid #00704a !important;
            color: white !important;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: none !important; /* Remove shadow */
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #modalSubmitBtn:hover {
            background: #005a37 !important; /* Darker green hover effect */
            border-color: #005a37 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 112, 74, 0.3) !important; /* Subtle green shadow on hover */
        }

        #modalSubmitBtn:active {
            transform: translateY(0);
            box-shadow: none !important;
        }

        #modalSubmitBtn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 112, 74, 0.25) !important; /* Green focus outline */
        }

        /* Ensure button text and icon are properly aligned */
        #modalSubmitBtn i {
            font-size: 14px;
        }

        /* Fix form field spacing */
        .enhanced-form-group {
            margin-bottom: 20px;
        }

        .enhanced-label {
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .enhanced-label i {
            color: #333333; /* Black icons */
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .enhanced-label:hover i {
            color: #00704a; /* Green on hover */
        }

        /* Fix select dropdown arrow positioning */
        .select-wrapper {
            position: relative;
        }

        .select-wrapper .enhanced-select {
            appearance: none;
            background-image: none;
        }

        .select-wrapper .select-icon {
            right: 15px;
            left: auto;
            pointer-events: none;
        }

        /* Fix textarea icon positioning */
        .textarea-wrapper {
            position: relative;
        }

        .textarea-icon {
            top: 20px !important;
            transform: none;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .input-wrapper .enhanced-input,
            .select-wrapper .enhanced-select,
            .textarea-wrapper .enhanced-textarea {
                padding-left: 40px !important;
            }

            .input-icon,
            .select-icon {
                left: 12px;
            }
        }
    </style>

    <!-- Universal Modals -->
    <?php include 'includes/universal-modals.php'; ?>

    <!-- Include necessary JavaScript files -->
    <script src="js/modal-handler.js"></script>
    <script src="js/multi-step-form.js"></script>
    <script src="js/universal-confirmation.js"></script>
    <?php require_once '../includes/footer_includes.php'; ?>
</body>
</html>