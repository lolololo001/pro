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

// Check if teachers table exists
$result = $conn->query("SHOW TABLES LIKE 'teachers'");
if ($result->num_rows == 0) {
    // Create teachers table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        subject VARCHAR(100),
        qualification VARCHAR(255),
        department_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(dep_id) ON DELETE SET NULL
    )");
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $teacher_id = intval($_GET['id']);
    
    // Delete the teacher
    $stmt = $conn->prepare("DELETE FROM teachers WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $teacher_id, $school_id);
    
    if ($stmt->execute()) {
        $_SESSION['teacher_success'] = 'Teacher deleted successfully!';
    } else {
        $_SESSION['teacher_error'] = 'Failed to delete teacher: ' . $conn->error;
    }
    
    $stmt->close();
    header('Location: teachers.php');
    exit;
}

// Get all teachers for this school with department information
$teachers = [];
try {
    // First check if department_id column exists in teachers table
    $result = $conn->query("SHOW COLUMNS FROM teachers LIKE 'department_id'");
    
    if ($result->num_rows == 0) {
        // Add department_id column if it doesn't exist
        $conn->query("ALTER TABLE teachers ADD COLUMN department_id INT NULL, ADD FOREIGN KEY (department_id) REFERENCES departments(dep_id) ON DELETE SET NULL");
        echo "<div class='alert alert-success'>The teachers table has been updated with department support.</div>";
    }
    
    // Now we can safely use department_id in the join
    $stmt = $conn->prepare("SELECT t.*, d.department_name 
                      FROM teachers t 
                      LEFT JOIN departments d ON t.department_id = d.dep_id 
                      WHERE t.school_id = ? 
                      ORDER BY t.name ASC");
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching teachers: " . $e->getMessage());
}

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
    <title>Manage Teachers - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- <link rel="stylesheet" href="css/universal-confirmation.css"> -->
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
            .form-grid {
                grid-template-columns: 1fr;
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
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-chalkboard-teacher"></i> Manage Teachers</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <span>Teachers</span>
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
        

        <!-- Teachers List Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> All Teachers</h2>
                <!-- Action buttons positioned at the right -->
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <button onclick="exportTeacherData()" class="btn export-btn" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; font-size: 0.9rem; border-radius: 6px; background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057; transition: all 0.3s ease;">
                        <i class="fas fa-download"></i> Export List
                    </button>
                    <button class="btn btn-primary" onclick="openModal('addTeacherModal')" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; font-size: 0.9rem; border-radius: 6px; transition: all 0.3s ease;">
                        <i class="fas fa-user-plus"></i> Add Teacher
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($teachers) > 0): ?>
                    <!-- Search Box -->
                    <div class="search-container" data-table="teachers-table">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" placeholder="Search teachers...">
                            <button type="button" class="search-clear" onclick="clearSearch('teachers-table')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <br>
                    <div class="table-responsive">
                        <table class="data-table" id="teachers-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Subject</th>
                                    <th>Department</th>
                                    <th>Qualification</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['department_name'] ?? 'Not assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($teacher['qualification'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="javascript:void(0)"
                                                   class="btn-icon edit"
                                                   title="Edit <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>"
                                                   data-id="<?php echo $teacher['id']; ?>"
                                                   data-type="teacher"
                                                   data-name="<?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>"
                                                   data-modal="true">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="javascript:void(0)"
                                                   class="btn-icon delete"
                                                   title="Delete <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>"
                                                   data-id="<?php echo $teacher['id']; ?>"
                                                   data-type="teacher"
                                                   data-name="<?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Empty search results message -->
                        <div id="teachers-table-empty-search" class="empty-search-results" style="display: none;">
                            <i class="fas fa-search"></i>
                            <p>No teachers found matching your search.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
                        <div class="empty-text">No teachers found</div>
                        <p>Start by adding a new teacher using the form above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Teacher Edit Modal -->
    <div id="teacherEditModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Teacher</h2>
                <span class="close" onclick="closeTeacherEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="teacherEditFormContainer"></div>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Confirmation</h2>
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
    
    <style>
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
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
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
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .close {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #f8c301;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 2rem;
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .loading-spinner i {
            margin-right: 0.5rem;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .modal-form .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .section-title {
            font-size: 1.1rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
    
    <!-- Include search.js for search functionality -->
    <script src="js/search.js"></script>
    
    <script>
        // Global variables
        let currentTeacherId = null;
        let confirmCallback = null;
        
        // Open teacher edit form
        function openTeacherEditForm(teacherId) {
            currentTeacherId = teacherId;
            const modal = document.getElementById('teacherEditModal');
            const formContainer = document.getElementById('teacherEditFormContainer');
            
            // Show loading spinner
            formContainer.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            modal.style.display = 'block';
            
            // Fetch teacher data
            fetch(`get_edit_form.php?type=teacher&id=${teacherId}`)
                .then(response => response.text())
                .then(html => {
                    formContainer.innerHTML = html;
                    
                    // Add event listener to the form
                    const form = document.getElementById('editForm');
                    if (form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            confirmUpdateTeacher(new FormData(form));
                        });
                    }
                })
                .catch(error => {
                    formContainer.innerHTML = `<div class="alert alert-danger">Error loading form: ${error.message}</div>`;
                });
        }
        
        // Close teacher edit modal
        function closeTeacherEditModal() {
            document.getElementById('teacherEditModal').style.display = 'none';
            currentTeacherId = null;
        }
        
        // Confirm teacher update
        function confirmUpdateTeacher(formData) {
            const modal = document.getElementById('confirmationModal');
            const message = document.getElementById('confirmationMessage');
            const confirmBtn = document.getElementById('confirmButton');
            message.innerHTML = 'Are you sure you want to update this teacher information?';
            modal.style.display = 'block';
            // Set up the confirm button action
            confirmCallback = function() {
                // Actually update the teacher after confirmation
                updateTeacher(formData);
                closeConfirmationModal();
            };
            confirmBtn.onclick = function() {
                if (confirmCallback) confirmCallback();
            };
        }
        
        // Update teacher
        function updateTeacher(formData) {
            if (!currentTeacherId) return;
            
            // Add teacher ID to form data
            formData.append('id', currentTeacherId);
            
            // Send update request
            fetch('edit_teacher.php?id=' + currentTeacherId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Close the edit modal
                closeTeacherEditModal();
                
                // Reload the page to show updated data
                window.location.reload();
            })
            .catch(error => {
                alert('Error updating teacher: ' + error.message);
            });
        }
        
        // Confirm delete teacher
        function confirmDeleteTeacher(teacherId) {
            currentTeacherId = teacherId;
            const modal = document.getElementById('confirmationModal');
            const message = document.getElementById('confirmationMessage');
            const confirmBtn = document.getElementById('confirmButton');
            message.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #f44336;"></i> Are you sure you want to delete this teacher? This action cannot be undone.';
            modal.style.display = 'block';
            confirmCallback = function() {
                // AJAX delete
                const formData = new FormData();
                formData.append('id', teacherId);
                formData.append('type', 'teacher');
                fetch('delete_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the teacher row from the table
                        const row = document.querySelector(`a.btn-icon.delete[data-id='${teacherId}']`).closest('tr');
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(() => { row.remove(); }, 300);
                        }
                        showAlert('success', '<i class="fas fa-check-circle"></i> Teacher deleted successfully.');
                    } else {
                        showAlert('danger', '<i class="fas fa-exclamation-circle"></i> Error: ' + (data.message || 'Failed to delete teacher'));
                    }
                })
                .catch(() => {
                    showAlert('danger', '<i class="fas fa-exclamation-circle"></i> An error occurred while deleting the teacher.');
                })
                .finally(() => {
                    closeConfirmationModal();
                });
            };
            confirmBtn.onclick = function() {
                if (confirmCallback) confirmCallback();
            };
        // Show alert function (copied from students.php for consistency)
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
            document.body.appendChild(alertDiv);
            setTimeout(() => {
                alertDiv.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    if (alertDiv.parentNode) alertDiv.remove();
                }, 300);
            }, 5000);
        }
        }
        
        // Close confirmation modal
        function closeConfirmationModal() {
            document.getElementById('confirmationModal').style.display = 'none';
            confirmCallback = null;
        }
        
        // Initialize edit buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to edit buttons
            const editButtons = document.querySelectorAll('a.btn-icon.edit');
            editButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const teacherId = this.getAttribute('data-id');
                    openTeacherEditForm(teacherId);
                });
            });
            
            // Add event listeners to delete buttons
            const deleteButtons = document.querySelectorAll('a.btn-icon.delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const teacherId = this.getAttribute('data-id');
                    confirmDeleteTeacher(teacherId);
                });
            });
        });

        // Export teacher data function
        function exportTeacherData() {
            const table = document.getElementById('teachers-table');
            const rows = table.querySelectorAll('tbody tr:not([style*="display: none"])');

            if (rows.length === 0) {
                alert('No teachers to export');
                return;
            }

            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Name,Email,Phone,Subject,Department,Qualification,Date Added\n";

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = [
                    cells[0].textContent.trim(),
                    cells[1].textContent.trim(),
                    cells[2].textContent.trim(),
                    cells[3].textContent.trim(),
                    cells[4].textContent.trim(),
                    cells[5].textContent.trim(),
                    cells[6].textContent.trim()
                ];
                csvContent += rowData.map(field => `"${field}"`).join(',') + '\n';
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'teachers_list.csv');
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
    </script>

    <!-- Add Teacher Modal from Dashboard -->
    <div id="addTeacherModal" class="modal">
        <div class="modal-content" style="max-width: 700px; border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #00704a, #2563eb); color: white; padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.25rem;"><i class="fas fa-chalkboard-teacher"></i> Add New Teacher</h2>
                <span class="close-modal" onclick="closeModal('addTeacherModal')" style="color: white; font-size: 1.5rem; cursor: pointer; background: none; border: none;">&times;</span>
            </div>
            <div class="modal-body" style="padding: 2rem; background: white;">
                <?php if (isset($_SESSION['teacher_error'])): ?>
                    <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['teacher_error']; unset($_SESSION['teacher_error']); ?>
                    </div>
                <?php endif; ?>

                <form action="add_teacher.php" method="POST" class="modal-form" id="teacherForm">
                    <div class="form-section" style="margin-bottom: 2rem;">
                        <div class="section-header" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e9ecef;">
                            <div style="width: 40px; height: 40px; background: #00704a; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <h3 style="margin: 0; color: #00704a; font-size: 1.1rem;">Teacher Information</h3>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div style="margin-bottom: 1rem;">
                                <label for="teacher_name" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Full Name <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="text" id="teacher_name" name="teacher_name" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;" required>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label for="teacher_email" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Email <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="email" id="teacher_email" name="teacher_email" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;" required>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label for="teacher_phone" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Phone <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="tel" id="teacher_phone" name="teacher_phone" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;" required>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label for="teacher_subject" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Subject
                                </label>
                                <input type="text" id="teacher_subject" name="teacher_subject" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;">
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label for="teacher_qualification" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Qualification
                                </label>
                                <input type="text" id="teacher_qualification" name="teacher_qualification" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;">
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label for="teacher_department_id" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Department <span style="color: #dc3545;">*</span>
                                </label>
                                <select id="teacher_department_id" name="department_id" class="form-control"
                                        style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;" required>
                                    <option value="">Select Department</option>
                                    <?php
                                    // Get all departments for dropdown
                                    $departments = [];
                                    $conn = getDbConnection();
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
                                    $conn->close();

                                    foreach ($departments as $department): ?>
                                        <option value="<?php echo $department['dep_id']; ?>">
                                            <?php echo htmlspecialchars($department['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-start; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e9ecef;">
                        <button type="submit" id="saveTeacherBtn"
                                style="background: #00704a; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: background-color 0.3s ease;"
                                onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#00704a'">
                            <i class="fas fa-save"></i> Save Teacher
                        </button>
                        <button type="button" onclick="closeModal('addTeacherModal')"
                                style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- universal-confirmation and universal-modals removed to prevent double popups for delete -->
</body>
</html>